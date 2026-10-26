<?php

namespace BookStack\Uploads;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ExcelImportService
{
    public function convert(UploadedFile $upload): string
    {
        $extension = strtolower($upload->getClientOriginalExtension());
        (new ExcelImportPolicy())->validate($upload);
        $directory = sys_get_temp_dir() . '/bookstack-excel-import-' . bin2hex(random_bytes(16));
        if (!mkdir($directory, 0700)) {
            throw new RuntimeException('Không tạo được thư mục nhập Excel.');
        }
        try {
            $input = $directory . '/workbook.' . $extension;
            if (!copy($upload->getRealPath(), $input)) {
                throw new RuntimeException('Không đọc được bảng tính đã tải lên.');
            }
            $output = (new ExcelConversion())->run($directory, $input, 'html:HTML (StarCalc)');
            $html = file_get_contents($output);
            // DOM normalization and the editor retain multiple copies. A small
            // XLSX can expand into HTML too large for one synchronous page import.
            if (strlen($html) > 10 * 1024 * 1024) {
                throw new RuntimeException('Excel tạo ra hơn 10 MB HTML, quá lớn để chỉnh sửa trong một trang. Hãy tách sheet/vùng dữ liệu rồi nhập riêng.');
            }
            // Calc uses bgcolor for fills. Normalize it before the shared allowlist.
            $document = new \DOMDocument();
            $previous = libxml_use_internal_errors(true);
            try {
                $document->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_NONET);
            } finally {
                libxml_clear_errors(); libxml_use_internal_errors($previous);
            }
            foreach ((new \DOMXPath($document))->query('//*[@bgcolor]') as $element) {
                if ($element->hasAttribute('bgcolor')) {
                    $color = $element->getAttribute('bgcolor');
                    if (preg_match('/^#[a-f0-9]{3,6}$/i', $color)) {
                        $element->setAttribute('style', $element->getAttribute('style') . ';background-color:' . $color);
                    }
                }
            }
            $body = $document->getElementsByTagName('body')->item(0);
            if (!$body) { throw new RuntimeException('Không tìm thấy nội dung bảng tính.'); }
            $normalized = $document->saveHTML($body);
            unset($body, $element, $document);
            $clean = (new WordImportService())->cleanHtml($normalized, $directory, [], 100000);
            return '<a name="bookstack-excel-content"></a>' . $clean;
        } finally {
            File::deleteDirectory($directory);
        }
    }

}
