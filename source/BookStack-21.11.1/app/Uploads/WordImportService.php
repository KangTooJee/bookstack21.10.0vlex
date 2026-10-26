<?php

namespace BookStack\Uploads;

use DOMDocument;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class WordImportService
{
    public function convert(UploadedFile $upload): string
    {
        $extension = strtolower($upload->getClientOriginalExtension());
        if (!in_array($extension, ['doc', 'docx'], true)) {
            throw new RuntimeException('Chỉ hỗ trợ tài liệu .doc và .docx.');
        }

        $directory = sys_get_temp_dir() . '/bookstack-word-' . bin2hex(random_bytes(16));
        if (!mkdir($directory, 0700)) {
            throw new RuntimeException('Không tạo được thư mục chuyển đổi tài liệu.');
        }

        try {
            $input = $directory . '/document.' . $extension;
            if (!copy($upload->getRealPath(), $input)) {
                throw new RuntimeException('Không đọc được tài liệu đã tải lên.');
            }
            $this->validateFormat($input, $extension);
            $listIndents = $extension === 'docx' ? $this->prepareDocx($input) : [];

            // A separate profile prevents concurrent imports sharing a running office process.
            mkdir($directory . '/profile/user', 0700, true);
            file_put_contents($directory . '/profile/user/registrymodifications.xcu',
                '<?xml version="1.0"?><oor:items xmlns:oor="http://openoffice.org/2001/registry">' .
                '<item oor:path="/org.openoffice.Office.Common/Security/Scripting"><prop oor:name="MacroSecurityLevel" oor:op="fuse"><value>3</value></prop></item>' .
                '<item oor:path="/org.openoffice.Office.Writer/Content/Update"><prop oor:name="Link" oor:op="fuse"><value>2</value></prop></item></oor:items>');
            $process = new Process([
                'libreoffice', '-env:UserInstallation=file://' . $directory . '/profile',
                '--headless', '--nologo', '--nodefault', '--norestore',
                '--convert-to', 'html:HTML (StarWriter)', '--outdir', $directory, $input,
            ], $directory);
            $process->setTimeout(60);
            try {
                $process->run();
            } catch (ProcessTimedOutException $exception) {
                throw new RuntimeException('Tài liệu mất quá 60 giây để xử lý. Hãy chia nhỏ tài liệu rồi thử lại.');
            }

            $output = $directory . '/document.html';
            if (!$process->isSuccessful() || !is_file($output)) {
                throw new RuntimeException('Không chuyển đổi được tài liệu. Hãy kiểm tra file có bị lỗi hoặc đặt mật khẩu không.');
            }
            if (filesize($output) > 20 * 1024 * 1024) {
                throw new RuntimeException('Nội dung HTML quá lớn. Hãy chia nhỏ tài liệu.');
            }

            return $this->cleanHtml(file_get_contents($output), $directory, $listIndents);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    private function validateFormat(string $path, string $extension): void
    {
        if ($extension === 'doc') {
            $handle = fopen($path, 'rb');
            $signature = fread($handle, 8);
            fclose($handle);
            if ($signature !== hex2bin('d0cf11e0a1b11ae1')) {
                throw new RuntimeException('File .doc không đúng định dạng Word 97–2003. Hãy lưu lại thành .docx.');
            }
            return;
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('File .docx không hợp lệ hoặc đã hỏng.');
        }
        try {
            $expanded = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $expanded += $zip->statIndex($index)['size'];
            }
            if ($zip->locateName('word/document.xml') === false || $zip->numFiles > 5000 || $expanded > 100 * 1024 * 1024) {
                throw new RuntimeException('Tài liệu .docx không hợp lệ hoặc quá lớn sau giải nén.');
            }
        } finally {
            $zip->close();
        }
    }

    private function prepareDocx(string $path): array
    {
        $zip = new \ZipArchive();
        $zip->open($path);
        try {
            $wordNamespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
            $document = new DOMDocument();
            if (!$document->loadXML($zip->getFromName('word/document.xml'), LIBXML_NONET)) {
                throw new RuntimeException('Không đọc được nội dung tài liệu Word.');
            }
            $xpath = new \DOMXPath($document);
            $xpath->registerNamespace('w', $wordNamespace);
            $xpath->registerNamespace('o', 'urn:schemas-microsoft-com:office:office');

            // OLE packages export as anonymous preview GIFs, not document images.
            foreach (iterator_to_array($xpath->query('//w:object[.//o:OLEObject]')) as $object) {
                $object->parentNode->removeChild($object);
            }

            $numbering = new DOMDocument();
            $numberingXml = $zip->getFromName('word/numbering.xml');
            $indents = [];
            if ($numberingXml !== false && $numbering->loadXML($numberingXml, LIBXML_NONET)) {
                $numbers = new \DOMXPath($numbering);
                $numbers->registerNamespace('w', $wordNamespace);
                $prefix = '_bslist_' . bin2hex(random_bytes(6)) . '_';
                $bookmarkId = 0;
                foreach ($xpath->query('//w:bookmarkStart|//w:bookmarkEnd') as $bookmark) {
                    $bookmarkId = max($bookmarkId, (int) $bookmark->getAttributeNS($wordNamespace, 'id') + 1);
                }
                foreach ($xpath->query('//w:body//w:p[w:pPr/w:numPr/w:numId]') as $paragraph) {
                    $numId = (int) $xpath->evaluate('string(w:pPr/w:numPr/w:numId/@w:val)', $paragraph);
                    $level = (int) $xpath->evaluate('string(w:pPr/w:numPr/w:ilvl/@w:val)', $paragraph);
                    $abstractId = $numbers->evaluate('string(/w:numbering/w:num[@w:numId="' . $numId . '"]/w:abstractNumId/@w:val)');
                    if ($abstractId === '' || !ctype_digit($abstractId)) {
                        continue;
                    }
                    $base = '/w:numbering/w:abstractNum[@w:abstractNumId="' . $abstractId . '"]/w:lvl[@w:ilvl="' . $level . '"]';
                    $override = '/w:numbering/w:num[@w:numId="' . $numId . '"]/w:lvlOverride[@w:ilvl="' . $level . '"]/w:lvl';
                    $left = $numbers->evaluate('string(' . $base . '/w:pPr/w:ind/@w:start)');
                    $left = $left !== '' ? $left : $numbers->evaluate('string(' . $base . '/w:pPr/w:ind/@w:left)');
                    $overrideLeft = $numbers->evaluate('string(' . $override . '/w:pPr/w:ind/@w:start)');
                    $overrideLeft = $overrideLeft !== '' ? $overrideLeft : $numbers->evaluate('string(' . $override . '/w:pPr/w:ind/@w:left)');
                    $directLeft = $xpath->evaluate('string(w:pPr/w:ind/@w:start)', $paragraph);
                    $directLeft = $directLeft !== '' ? $directLeft : $xpath->evaluate('string(w:pPr/w:ind/@w:left)', $paragraph);
                    $left = $directLeft !== '' ? $directLeft : ($overrideLeft !== '' ? $overrideLeft : $left);
                    if ($left === '' || !preg_match('/^-?[0-9]+$/', $left)) {
                        continue;
                    }
                    $name = $prefix . count($indents);
                    $indents[$name] = (int) $left;
                    $start = $document->createElementNS($wordNamespace, 'w:bookmarkStart');
                    $start->setAttributeNS($wordNamespace, 'w:id', (string) $bookmarkId);
                    $start->setAttributeNS($wordNamespace, 'w:name', $name);
                    $end = $document->createElementNS($wordNamespace, 'w:bookmarkEnd');
                    $end->setAttributeNS($wordNamespace, 'w:id', (string) $bookmarkId++);
                    $properties = $xpath->query('w:pPr', $paragraph)->item(0);
                    $paragraph->insertBefore($start, $properties->nextSibling);
                    $paragraph->insertBefore($end, $start->nextSibling);
                }
            }
            if (!$zip->addFromString('word/document.xml', $document->saveXML())) {
                throw new RuntimeException('Không chuẩn bị được tài liệu Word để chuyển đổi.');
            }
            return $indents;
        } finally {
            $zip->close();
        }
    }

    private function restoreListIndents(\DOMElement $body, array $indents): void
    {
        if (!$indents) {
            return;
        }
        $items = new \SplObjectStorage();
        foreach ((new \DOMXPath($body->ownerDocument))->query('.//a[@name]', $body) as $anchor) {
            $name = $anchor->getAttribute('name');
            if (!array_key_exists($name, $indents)) {
                continue;
            }
            $item = $anchor->parentNode;
            while ($item !== $body && strtolower($item->nodeName) !== 'li') {
                $item = $item->parentNode;
            }
            if ($item !== $body) {
                $items[$item] = $indents[$name];
            }
            $anchor->parentNode->removeChild($anchor);
        }
        foreach ($items as $item) {
            $parentLeft = 0;
            for ($parent = $item->parentNode; $parent !== $body; $parent = $parent->parentNode) {
                if ($items->contains($parent)) {
                    $parentLeft = $items[$parent];
                    break;
                }
                if (in_array(strtolower($parent->nodeName), ['ul', 'ol'], true)) {
                    $parent->setAttribute('style', $parent->getAttribute('style') . '; margin-left: 0; padding-left: 0');
                }
            }
            // Word stores absolute text positions in twips; nested HTML uses
            // offsets relative to its containing list item's text position.
            $left = ($items[$item] - $parentLeft) / 1440;
            $item->setAttribute('style', $item->getAttribute('style') . '; margin-left: ' . $left . 'in; padding-left: 0; text-indent: 0');
            foreach ($item->childNodes as $child) {
                if ($child instanceof \DOMElement && $child->tagName === 'p') {
                    $child->setAttribute('style', $child->getAttribute('style') . '; margin-left: 0; text-indent: 0');
                }
            }
        }
    }

    // Shared by office imports/exports; image paths must resolve inside the conversion directory.
    public function cleanHtml(string $html, string $directory, array $listIndents = [], int $maxElements = 0): string
    {
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $body = $doc->getElementsByTagName('body')->item(0);
        if (!$body) {
            throw new RuntimeException('Không tìm thấy nội dung trong tài liệu.');
        }

        $this->removeFloatingDrawingArtifacts($body);
        $this->restoreListIndents($body, $listIndents);

        $allowed = explode(' ', 'p div span h1 h2 h3 h4 h5 h6 table thead tbody tfoot tr td th caption colgroup col ol ul li a img strong b em i u s strike sup sub br hr blockquote pre code font dl dt dd');
        // XPath returns a static node set. Materializing a live DOM tag list can
        // repeatedly rescan large Calc tables and exhaust the HTTP time limit.
        $nodes = iterator_to_array((new \DOMXPath($doc))->query('.//*', $body));
        if ($maxElements > 0 && count($nodes) > $maxElements) {
            throw new RuntimeException('Nội dung có quá 100.000 phần tử HTML để chỉnh sửa trong một trang. Hãy tách các sheet hoặc vùng dữ liệu thành file nhỏ hơn.');
        }
        $imageBytes = 0;
        foreach (array_reverse($nodes) as $node) {
            $tag = strtolower($node->tagName);
            if (!in_array($tag, $allowed, true)) {
                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'link', 'meta'], true)) {
                    $node->parentNode->removeChild($node);
                } else {
                    while ($node->firstChild) {
                        $node->parentNode->insertBefore($node->firstChild, $node);
                    }
                    $node->parentNode->removeChild($node);
                }
                continue;
            }
            $src = $tag === 'img' ? $node->getAttribute('src') : '';
            foreach (iterator_to_array($node->attributes) as $attribute) {
                $name = strtolower($attribute->name);
                $value = $attribute->value;
                $keep = in_array($name, ['title', 'alt', 'colspan', 'rowspan', 'width', 'height', 'start', 'type', 'align', 'valign', 'border', 'cellpadding', 'cellspacing', 'color', 'face', 'size'], true);
                if ($name === 'style') {
                    $node->setAttribute('style', $this->cleanStyle($value));
                    $keep = true;
                } elseif ($name === 'href' && $tag === 'a') {
                    $keep = preg_match('~^(https?://|mailto:|#)~i', trim($value)) === 1;
                } elseif ($name === 'name' && $tag === 'a') {
                    $keep = true;
                } elseif ($name === 'id') {
                    // Named anchors survive BookStack's HTML ID normalization, including nested bookmarks.
                    $anchor = $doc->createElement('a');
                    $anchor->setAttribute('name', $value);
                    $node->parentNode->insertBefore($anchor, $node);
                }
                if (!$keep) {
                    $node->removeAttribute($attribute->name);
                }
            }
            if ($tag === 'img') {
                $image = realpath($directory . '/' . rawurldecode($src));
                if (!$image || strpos($image, $directory . '/') !== 0 || !is_file($image)) {
                    throw new RuntimeException('Ảnh trong tài liệu không được nhúng hoặc không đọc được. Hãy nhúng ảnh vào tài liệu rồi thử lại.');
                }
                $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($image);
                $imageBytes += filesize($image);
                if (!in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true) || $imageBytes > 30 * 1024 * 1024 || !@getimagesize($image)) {
                    throw new RuntimeException('Ảnh không được hỗ trợ hoặc tổng dung lượng ảnh vượt quá 30 MB.');
                }
                $node->setAttribute('src', 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($image)));
            }
        }

        $result = '';
        foreach ($body->childNodes as $child) {
            $result .= $doc->saveHTML($child);
        }
        if (trim(strip_tags($result)) === '' && strpos($result, '<img') === false) {
            throw new RuntimeException('Tài liệu không có nội dung có thể nhập.');
        }

        return $result;
    }

    private function removeFloatingDrawingArtifacts(\DOMElement $body): void
    {
        // Writer's HTML export flattens floating drawing overlays into ShapeN
        // images (without dimensions) and FrameN text boxes. Their page-relative
        // placement cannot survive in page HTML, so they become stray content.
        // Remove whole frames before sanitizing styles or creating bookmark anchors.
        foreach ((new \DOMXPath($body->ownerDocument))->query('.//span[@id] | .//img[@name]', $body) as $node) {
            $tag = strtolower($node->tagName);
            $floatingFrame = $tag === 'span'
                && preg_match('/^Frame[0-9]+$/', $node->getAttribute('id'))
                && preg_match('/(?:^|;)\s*float\s*:\s*(?:left|right)\s*(?:;|$)/i', $node->getAttribute('style'));
            $drawingPlaceholder = $tag === 'img'
                && preg_match('/^Shape[0-9]+$/', $node->getAttribute('name'))
                && $node->getAttribute('alt') === $node->getAttribute('name')
                && in_array(strtolower($node->getAttribute('align')), ['left', 'right'], true)
                && !$node->hasAttribute('width') && !$node->hasAttribute('height');

            if ($floatingFrame || $drawingPlaceholder) {
                $node->parentNode->removeChild($node);
            }
        }
    }

    private function cleanStyle(string $style): string
    {
        $result = [];
        foreach (explode(';', $style) as $declaration) {
            $parts = explode(':', $declaration, 2);
            if (count($parts) !== 2) {
                continue;
            }
            [$property, $value] = array_map('trim', $parts);
            if (preg_match('/^(color|background-color|text-align|text-decoration|text-indent|font-(size|weight|style|family)|vertical-align|white-space|line-height|border(-[a-z-]+)?|padding(-[a-z]+)?|margin(-[a-z]+)?|width|height)$/i', $property)
                && preg_match('/^[a-zA-Z0-9\s#.,%()\x27"-]+$/', $value)
                && !preg_match('/url|expression|var\s*\(/i', $value)) {
                $result[] = $property . ': ' . $value;
            }
        }

        return implode('; ', $result);
    }
}
