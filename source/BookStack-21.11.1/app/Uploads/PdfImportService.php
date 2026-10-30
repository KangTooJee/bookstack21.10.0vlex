<?php

namespace BookStack\Uploads;

use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PdfImportService
{
    // Validate the upload envelope only. Never rewrite, decrypt, render or extract it.
    public function validate(UploadedFile $upload): void
    {
        if (strtolower($upload->getClientOriginalExtension()) !== 'pdf' || $upload->getSize() > 20 * 1024 * 1024) {
            throw new RuntimeException('Chỉ hỗ trợ file .pdf tối đa 20 MB.');
        }
        $path = $upload->getRealPath();
        $handle = fopen($path, 'rb');
        try {
            $header = fread($handle, 5);
            fseek($handle, max(0, filesize($path) - 4096));
            $tail = stream_get_contents($handle);
        } finally {
            fclose($handle);
        }
        if ($header !== '%PDF-' || strpos($tail, '%%EOF') === false || (new \finfo(FILEINFO_MIME_TYPE))->file($path) !== 'application/pdf') {
            throw new RuntimeException('File không đúng định dạng PDF hoặc chưa tải lên đầy đủ.');
        }
    }
}
