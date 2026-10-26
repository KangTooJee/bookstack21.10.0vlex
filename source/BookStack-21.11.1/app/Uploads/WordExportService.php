<?php

namespace BookStack\Uploads;

use BookStack\Entities\Models\Page;
use BookStack\Entities\Tools\PageContent;
use DOMDocument;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class WordExportService
{
    private $images;

    public function __construct(ImageService $images)
    {
        $this->images = $images;
    }

    public function export(Page $page): string
    {
        $directory = sys_get_temp_dir() . '/bookstack-word-export-' . bin2hex(random_bytes(16));
        if (!mkdir($directory, 0700)) {
            throw new RuntimeException('Không tạo được thư mục xuất Word.');
        }
        try {
            $html = (new PageContent($page))->render();
            if (strlen($html) > 20 * 1024 * 1024) {
                throw new RuntimeException('Nội dung quá lớn để xuất Word. Hãy chia nhỏ trang.');
            }
            $listIndents = [];
            $appearance = [];
            file_put_contents($directory . '/document.html', $this->prepareHtml($html, $page->name, $directory, $listIndents, $appearance));
            mkdir($directory . '/profile/user', 0700, true);
            file_put_contents($directory . '/profile/user/registrymodifications.xcu',
                '<?xml version="1.0"?><oor:items xmlns:oor="http://openoffice.org/2001/registry">' .
                '<item oor:path="/org.openoffice.Office.Common/Security/Scripting"><prop oor:name="MacroSecurityLevel" oor:op="fuse"><value>3</value></prop></item>' .
                '<item oor:path="/org.openoffice.Office.Writer/Content/Update"><prop oor:name="Link" oor:op="fuse"><value>2</value></prop></item></oor:items>');
            $process = new Process([
                'libreoffice', '-env:UserInstallation=file://' . $directory . '/profile',
                '--headless', '--nologo', '--nodefault', '--norestore',
                '--convert-to', 'docx:Office Open XML Text', '--outdir', $directory, $directory . '/document.html',
            ], $directory);
            $process->setTimeout(60);
            try {
                $process->run();
            } catch (ProcessTimedOutException $exception) {
                throw new RuntimeException('Xuất Word mất quá 60 giây. Hãy chia nhỏ trang rồi thử lại.');
            }
            $output = $directory . '/document.docx';
            if (!$process->isSuccessful() || !is_file($output)) {
                throw new RuntimeException('Không xuất được file Word. Vui lòng thử lại.');
            }
            $this->embedImages($output, $directory, $listIndents, $appearance);
            if (filesize($output) > 30 * 1024 * 1024) {
                throw new RuntimeException('File Word vượt quá 30 MB. Hãy chia nhỏ trang.');
            }
            return file_get_contents($output);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    private function embedImages(string $path, string $directory, array $listIndents, array $appearance): void
    {
        // Writer imports HTML images as linked files. Package them before the
        // temporary directory is removed so the download opens independently.
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('File Word xuất ra không hợp lệ.');
        }
        try {
            $relationships = new DOMDocument();
            $relationships->loadXML($zip->getFromName('word/_rels/document.xml.rels'), LIBXML_NONET);
            $types = new DOMDocument();
            $types->loadXML($zip->getFromName('[Content_Types].xml'), LIBXML_NONET);
            foreach ($relationships->documentElement->childNodes as $relationship) {
                if (!$relationship instanceof \DOMElement || substr($relationship->getAttribute('Type'), -6) !== '/image'
                    || $relationship->getAttribute('TargetMode') !== 'External') {
                    continue;
                }
                $target = $relationship->getAttribute('Target');
                $file = strpos($target, 'file://') === 0 ? realpath(rawurldecode(substr($target, 7))) : false;
                if (!$file || strpos($file, $directory . '/') !== 0 || !is_file($file)) {
                    throw new RuntimeException('Không nhúng được ảnh vào file Word.');
                }
                $name = basename($file);
                $zip->addFile($file, 'word/media/' . $name);
                $relationship->setAttribute('Target', 'media/' . $name);
                $relationship->removeAttribute('TargetMode');
                $extension = pathinfo($name, PATHINFO_EXTENSION);
                $found = false;
                foreach ($types->getElementsByTagName('Default') as $type) {
                    $found = $found || $type->getAttribute('Extension') === $extension;
                }
                if (!$found) {
                    $type = $types->createElementNS($types->documentElement->namespaceURI, 'Default');
                    $type->setAttribute('Extension', $extension);
                    $type->setAttribute('ContentType', (new \finfo(FILEINFO_MIME_TYPE))->file($file));
                    $types->documentElement->appendChild($type);
                }
            }
            $zip->addFromString('word/_rels/document.xml.rels', $relationships->saveXML());
            $zip->addFromString('[Content_Types].xml', $types->saveXML());
            $this->restoreListIndents($zip, $listIndents, $appearance);
        } finally {
            $zip->close();
        }
    }

    private function restoreListIndents(\ZipArchive $zip, array $indents, array $appearance): void
    {
        $word = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $document = new DOMDocument();
        $document->loadXML($zip->getFromName('word/document.xml'), LIBXML_NONET);
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('w', $word);
        $relationships = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $xpath->registerNamespace('r', $relationships);
        $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
        foreach ($xpath->query('//a:blip[@r:link]') as $image) {
            $image->setAttributeNS($relationships, 'r:embed', $image->getAttributeNS($relationships, 'link'));
            $image->removeAttributeNS($relationships, 'link');
        }
        foreach (iterator_to_array($xpath->query('//w:bookmarkStart')) as $bookmark) {
            $name = $bookmark->getAttributeNS($word, 'name');
            if (!isset($indents[$name])) {
                continue;
            }
            $paragraph = $bookmark->parentNode;
            $properties = $xpath->query('w:pPr', $paragraph)->item(0);
            if (!$properties) {
                $properties = $document->createElementNS($word, 'w:pPr');
                $paragraph->insertBefore($properties, $paragraph->firstChild);
            }
            $indent = $xpath->query('w:ind', $properties)->item(0);
            if (!$indent) {
                $indent = $document->createElementNS($word, 'w:ind');
                $properties->appendChild($indent);
            }
            $indent->removeAttributeNS($word, 'start');
            $indent->setAttributeNS($word, 'w:left', (string) $indents[$name]);
            foreach ($xpath->query('w:tabs/w:tab[@w:val="left"]', $properties) as $tab) {
                $tab->setAttributeNS($word, 'w:pos', (string) $indents[$name]);
            }
            $id = $bookmark->getAttributeNS($word, 'id');
            foreach (iterator_to_array($xpath->query('//w:bookmarkEnd[@w:id="' . (int) $id . '"]')) as $end) {
                $end->parentNode->removeChild($end);
            }
            $bookmark->parentNode->removeChild($bookmark);
        }
        (new WordExportLayout())->fitDocument($document);
        (new WordExportAppearance())->applyDocument($document, $appearance);
        $zip->addFromString('word/document.xml', $document->saveXML());
    }

    private function cssLength(string $style, string $property, int $default = 0): int
    {
        if (!preg_match_all('/(?:^|;)\s*' . preg_quote($property, '/') . '\s*:\s*(-?[0-9.]+)\s*(in|cm|mm|pt|px|pc)?\s*(?=;|$)/i', $style, $matches, PREG_SET_ORDER)) {
            return $default;
        }
        $last = end($matches);
        $units = ['in' => 1440, 'cm' => 1440 / 2.54, 'mm' => 1440 / 25.4, 'pt' => 20, 'px' => 15, 'pc' => 240, '' => 15];
        return (int) round((float) $last[1] * $units[strtolower($last[2] ?? '')]);
    }

    private function prepareHtml(string $html, string $title, string $directory, array &$listIndents, array &$appearance = []): string
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML(mb_convert_encoding('<body>' . $html . '</body>', 'HTML-ENTITIES', 'UTF-8'), LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $body = $document->getElementsByTagName('body')->item(0);
        $allowed = explode(' ', 'p div span h1 h2 h3 h4 h5 h6 table thead tbody tfoot tr td th caption colgroup col ol ul li a img strong b em i u s strike sup sub br hr blockquote pre code font dl dt dd');
        // Eliminate active content and external resource loaders before conversion.
        foreach (array_reverse(iterator_to_array($body->getElementsByTagName('*'))) as $node) {
            if (!in_array(strtolower($node->tagName), $allowed, true)) {
                $node->parentNode->removeChild($node);
            }
        }
        $imageBytes = 0;
        $imageIndex = 0;
        foreach (iterator_to_array($body->getElementsByTagName('*')) as $node) {
            $tag = strtolower($node->tagName);
            $src = $tag === 'img' ? $node->getAttribute('src') : '';
            foreach (iterator_to_array($node->attributes) as $attribute) {
                $name = strtolower($attribute->name);
                if ($name === 'style') {
                    $styles = [];
                    foreach (explode(';', $attribute->value) as $declaration) {
                        $parts = explode(':', $declaration, 2);
                        if (count($parts) !== 2) {
                            continue;
                        }
                        [$property, $value] = array_map('trim', $parts);
                        if (preg_match('/^(color|background-color|text-align|text-decoration|text-indent|font-(size|weight|style|family)|vertical-align|white-space|line-height|border(-[a-z-]+)?|padding(-[a-z]+)?|margin(-[a-z]+)?|(max-)?width|(max-)?height|list-style-type)$/i', $property)
                            && preg_match('/^[\p{L}\p{N}\s#.,%()\x27"-]+$/u', $value)
                            && !preg_match('/(?:url|expression|var)\s*\(/i', $value)) {
                            $styles[] = $property . ': ' . $value;
                        }
                    }
                    $node->setAttribute('style', implode('; ', $styles));
                } elseif ($name === 'href' && $tag === 'a' && preg_match('~^(https?://|mailto:|#)~i', trim($attribute->value))) {
                    continue;
                } elseif (!in_array($name, ['title', 'alt', 'id', 'name', 'span', 'colspan', 'rowspan', 'width', 'height', 'start', 'type', 'align', 'valign', 'border', 'cellpadding', 'cellspacing', 'color', 'face', 'size'], true)) {
                    $node->removeAttribute($attribute->name);
                }
            }
            if ($tag !== 'img') {
                continue;
            }
            $data = strpos($src, 'data:image/') === 0 ? $src : $this->images->imageUriToBase64($src);
            if (!$data || !preg_match('~^data:image/(png|jpe?g|gif|webp);base64,(.+)$~s', $data, $match)) {
                throw new RuntimeException('Có ảnh không thể nhúng vào Word. Hãy tải ảnh vào kho ảnh của trang rồi thử lại.');
            }
            $bytes = base64_decode($match[2], true);
            $size = $bytes === false ? false : @getimagesizefromstring($bytes);
            if (!$size || !in_array($size[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
                throw new RuntimeException('Ảnh trong trang không đúng định dạng hỗ trợ.');
            }
            $imageBytes += strlen($bytes);
            if ($imageBytes > 30 * 1024 * 1024) {
                throw new RuntimeException('Tổng dung lượng ảnh vượt quá 30 MB.');
            }
            $file = 'image-' . $imageIndex++ . '.' . image_type_to_extension($size[2], false);
            file_put_contents($directory . '/' . $file, $bytes);
            $node->setAttribute('src', $file);
            foreach (['width', 'height'] as $dimension) {
                $value = trim($node->getAttribute($dimension));
                if (preg_match('/^[0-9.]+(?:%|px|pt|in|cm|mm|pc)$/i', $value)) {
                    $node->setAttribute('style', $node->getAttribute('style') . '; ' . $dimension . ': ' . $value);
                    $node->removeAttribute($dimension);
                }
            }
            $width = (int) $node->getAttribute('width') ?: $size[0];
            $height = (int) $node->getAttribute('height') ?: $size[1] * $width / $size[0];
            $node->setAttribute('width', (string) $width);
            $node->setAttribute('height', (string) (int) round($height));
        }
        (new WordExportLayout())->prepareHtml($body);
        $appearance = (new WordExportAppearance())->prepareHtml($body);
        // Keep explicit imported list positions: Writer otherwise adds its own
        // default list offset to the li margin while reading HTML.
        $prefix = 'bsword' . bin2hex(random_bytes(6));
        foreach (iterator_to_array($body->getElementsByTagName('li')) as $item) {
            if (!preg_match('/(?:^|;)\s*margin-left\s*:/i', $item->getAttribute('style'))) {
                continue;
            }
            $left = 0;
            for ($parent = $item; $parent !== $body; $parent = $parent->parentNode) {
                if (!$parent instanceof \DOMElement) {
                    continue;
                }
                $style = $parent->getAttribute('style');
                $left += $this->cssLength($style, 'margin-left');
                $left += $this->cssLength($style, 'padding-left', in_array($parent->tagName, ['ul', 'ol'], true) ? 480 : 0);
            }
            $paragraph = null;
            foreach ($item->childNodes as $child) {
                if ($child instanceof \DOMElement && $child->tagName === 'p') {
                    $paragraph = $child;
                    break;
                }
            }
            if (!$paragraph) {
                continue;
            }
            $name = $prefix . count($listIndents);
            $listIndents[$name] = $left;
            $anchor = $document->createElement('a');
            $anchor->setAttribute('name', $name);
            $paragraph->insertBefore($anchor, $paragraph->firstChild);
        }
        $content = '';
        foreach ($body->childNodes as $child) {
            $content .= $document->saveHTML($child);
        }
        return '<!doctype html><html><head><meta charset="utf-8"><title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>' .
            '<style>@page { size: A4; margin: 0.8in } body { font-family: "Segoe UI", sans-serif; font-size: 10.5pt; color: #444 } ' .
            'h1 { font-size: 3.425em } h2 { font-size: 2.8275em } h3 { font-size: 2.333em } h4 { font-size: 1.666em } h5 { font-size: 1.4em } h6 { font-size: 1.2em } h1,h2,h3,h4,h5,h6 { font-weight: normal } p { margin: 0 0 8pt } ' .
            'ul, ol { padding-left: 24pt } li p { margin-top: 0 } table { border-collapse: collapse } ' .
            'td, th { border: 1px solid #aaa; padding: 5pt } pre, code { font-family: monospace } a { color: #467886 }</style></head><body>' .
            '<h1 style="font-family: Segoe UI; font-size: 36pt; font-weight: normal"><span style="font-family: Segoe UI; font-size: 36pt">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</span></h1>' . $content . '</body></html>';
    }
}
