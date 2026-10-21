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

            return $this->cleanHtml(file_get_contents($output), $directory);
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

    private function cleanHtml(string $html, string $directory): string
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

        $allowed = explode(' ', 'p div span h1 h2 h3 h4 h5 h6 table thead tbody tfoot tr td th caption colgroup col ol ul li a img strong b em i u s strike sup sub br hr blockquote pre code font dl dt dd');
        $nodes = iterator_to_array($body->getElementsByTagName('*'));
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
                    throw new RuntimeException('Ảnh trong tài liệu không được nhúng hoặc không đọc được. Hãy nhúng ảnh vào Word rồi thử lại.');
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

    private function cleanStyle(string $style): string
    {
        $result = [];
        foreach (explode(';', $style) as $declaration) {
            $parts = explode(':', $declaration, 2);
            if (count($parts) !== 2) {
                continue;
            }
            [$property, $value] = array_map('trim', $parts);
            if (preg_match('/^(color|background-color|text-align|text-decoration|font-(size|weight|style|family)|vertical-align|white-space|line-height|border(-[a-z-]+)?|padding(-[a-z]+)?|margin(-[a-z]+)?|width|height)$/i', $property)
                && preg_match('/^[a-zA-Z0-9\s#.,%()\x27"-]+$/', $value)
                && !preg_match('/url|expression|var\s*\(/i', $value)) {
                $result[] = $property . ': ' . $value;
            }
        }

        return implode('; ', $result);
    }
}
