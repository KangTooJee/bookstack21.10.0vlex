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
        if (!in_array($extension, ['xls', 'xlsx'], true)) {
            throw new RuntimeException('Chỉ hỗ trợ bảng tính .xls và .xlsx.');
        }
        if ($upload->getSize() > 20 * 1024 * 1024) {
            throw new RuntimeException('File Excel vượt quá 20 MB.');
        }
        $directory = sys_get_temp_dir() . '/bookstack-excel-import-' . bin2hex(random_bytes(16));
        if (!mkdir($directory, 0700)) {
            throw new RuntimeException('Không tạo được thư mục nhập Excel.');
        }
        try {
            $input = $directory . '/workbook.' . $extension;
            if (!copy($upload->getRealPath(), $input)) {
                throw new RuntimeException('Không đọc được bảng tính đã tải lên.');
            }
            $this->validateFormat($input, $extension);
            $output = (new ExcelConversion())->run($directory, $input, 'html:HTML (StarCalc)');
            $html = file_get_contents($output);
            // Calc uses bgcolor for fills. Normalize it before the shared allowlist.
            $document = new \DOMDocument();
            $previous = libxml_use_internal_errors(true);
            try {
                $document->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_NONET);
            } finally {
                libxml_clear_errors(); libxml_use_internal_errors($previous);
            }
            foreach ($document->getElementsByTagName('*') as $element) {
                if ($element->hasAttribute('bgcolor')) {
                    $color = $element->getAttribute('bgcolor');
                    if (preg_match('/^#[a-f0-9]{3,6}$/i', $color)) {
                        $element->setAttribute('style', $element->getAttribute('style') . ';background-color:' . $color);
                    }
                }
            }
            $body = $document->getElementsByTagName('body')->item(0);
            if (!$body) { throw new RuntimeException('Không tìm thấy nội dung bảng tính.'); }
            return (new WordImportService())->cleanHtml($document->saveHTML($body), $directory);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    private function validateFormat(string $path, string $extension): void
    {
        if ($extension === 'xls') {
            $handle = fopen($path, 'rb');
            $signature = fread($handle, 8);
            fclose($handle);
            if ($signature !== hex2bin('d0cf11e0a1b11ae1')) {
                throw new RuntimeException('File .xls không đúng định dạng Excel 97–2003. Hãy lưu lại thành .xlsx.');
            }
            return;
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('File .xlsx không hợp lệ hoặc đã hỏng.');
        }
        try {
            $expanded = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $expanded += $zip->statIndex($index)['size'];
            }
            if ($zip->locateName('xl/workbook.xml') === false || $zip->numFiles > 5000 || $expanded > 100 * 1024 * 1024) {
                throw new RuntimeException('Bảng tính .xlsx không hợp lệ hoặc quá lớn sau giải nén.');
            }
            // Never pass a macro-enabled workbook disguised as .xlsx to Calc.
            if ($zip->locateName('xl/vbaProject.bin') !== false) {
                throw new RuntimeException('Không hỗ trợ bảng tính có macro. Hãy lưu bản .xlsx không có macro.');
            }
        } finally {
            $zip->close();
        }
    }
}
