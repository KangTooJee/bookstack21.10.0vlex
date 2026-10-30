<?php

namespace BookStack\Uploads;

use DOMDocument;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Process\Process;
use ZipArchive;

class StorageFileInspector
{
    public function inspect(UploadedFile $file): array
    {
        $errors = [];
        $extension = strtolower($file->getClientOriginalExtension());
        if (!$file->isValid()) {
            return ['errors' => ['Upload không hoàn tất hoặc vượt giới hạn của máy chủ.'], 'warnings' => []];
        }
        if (!in_array($extension, config('vlex-storage.extensions'), true)) {
            $errors[] = 'Định dạng không được phép. Chỉ nhận DOC, DOCX, XLS, XLSX, PDF.';
        }
        if ($file->getSize() > config('vlex-storage.max_bytes')) {
            $errors[] = 'Vượt giới hạn ' . (config('vlex-storage.max_bytes') / 1048576) . ' MB/file.';
        }
        if ($file->getSize() === 0) {
            $errors[] = 'File rỗng, không đọc được.';
        }
        if ($errors) {
            return ['errors' => $errors, 'warnings' => []];
        }

        $directory = sys_get_temp_dir() . '/vlex-inspect-' . bin2hex(random_bytes(16));
        mkdir($directory, 0700);
        try {
            $input = $directory . '/document.' . $extension;
            if (!copy($file->getRealPath(), $input)) {
                throw new RuntimeException('Không đọc được file upload.');
            }
            if ($extension === 'pdf') {
                $warnings = $this->pdf($directory, $input);
            } else {
                $this->officeStructure($input, $extension);
                $this->officeReadable($directory, $input);
                $warnings = [in_array($extension, ['docx', 'xlsx'], true)
                    ? 'Đã thử đọc bằng LibreOffice. Mốc sử dụng: Microsoft Office 2010 trở lên; chưa kiểm chứng bố cục/tính năng bằng từng phiên bản Microsoft Office.'
                    : 'File dùng định dạng Office 97–2003 (DOC/XLS). Đã thử đọc bằng LibreOffice; cần kiểm tra lại trên Microsoft Office 2010 trở lên, nhất là font, bảng, công thức và đối tượng nhúng.'];
                if ($extension === 'docx') {
                    $zip = new ZipArchive();
                    if ($zip->open($input) === true) {
                        $settings = $zip->getFromName('word/settings.xml') ?: '';
                        $zip->close();
                        $xml = new DOMDocument();
                        if ($settings && @$xml->loadXML($settings, LIBXML_NONET)) {
                            $xpath = new \DOMXPath($xml);
                            $modes = $xpath->query('//*[local-name()="compatSetting"][@*[local-name()="name" and .="compatibilityMode"]]/@*[local-name()="val"]');
                            if ($modes->length && (int) $modes->item(0)->nodeValue < 14) {
                                $warnings[] = 'Tài liệu khai báo chế độ tương thích Word trước 2010. Nên mở kiểm tra và Save As bằng Office 2010 trở lên.';
                            }
                        }
                    }
                }
            }
            return ['errors' => [], 'warnings' => $warnings];
        } catch (RuntimeException $error) {
            return ['errors' => [$error->getMessage()], 'warnings' => []];
        } finally {
            File::deleteDirectory($directory);
        }
    }

    private function officeStructure(string $path, string $extension): void
    {
        if (in_array($extension, ['doc', 'xls'], true)) {
            $data = file_get_contents($path);
            $streams = $extension === 'doc' ? ['WordDocument'] : ['Workbook', 'Book'];
            $matches = false;
            foreach ($streams as $stream) {
                $matches = $matches || strpos($data, mb_convert_encoding($stream, 'UTF-16LE', 'UTF-8')) !== false;
            }
            if (substr($data, 0, 8) !== hex2bin('d0cf11e0a1b11ae1') || !$matches) {
                throw new RuntimeException('Nội dung không khớp định dạng ' . strtoupper($extension) . ' hoặc file bị hỏng.');
            }
            return;
        }
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CHECKCONS) !== true) {
            throw new RuntimeException('Gói Office bị hỏng hoặc được mã hóa/đặt mật khẩu.');
        }
        try {
            $main = $extension === 'docx' ? 'word/document.xml' : 'xl/workbook.xml';
            foreach (['[Content_Types].xml', '_rels/.rels', $main] as $required) {
                if ($zip->locateName($required) === false) {
                    throw new RuntimeException('Nội dung không khớp định dạng ' . strtoupper($extension) . '.');
                }
            }
            $total = 0;
            if ($zip->numFiles > 10000) {
                throw new RuntimeException('Gói Office có quá nhiều thành phần để kiểm tra.');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $total += $stat['size'];
                if ($total > 200 * 1048576 || ($stat['encryption_method'] ?? 0) !== 0) {
                    throw new RuntimeException('Gói Office được mã hóa hoặc vượt giới hạn giải nén kiểm tra 200 MB.');
                }
                $content = $zip->getFromIndex($i);
                if ($content === false || sprintf('%u', crc32($content)) !== sprintf('%u', $stat['crc'])) {
                    throw new RuntimeException('Gói Office bị hỏng (sai checksum).');
                }
                if (preg_match('/\.(xml|rels)$/i', $stat['name'])) {
                    $xml = new DOMDocument();
                    if (stripos($content, '<!DOCTYPE') !== false || !@$xml->loadXML($content, LIBXML_NONET)) {
                        throw new RuntimeException('Thành phần XML trong file Office bị lỗi.');
                    }
                }
            }
        } finally {
            $zip->close();
        }
    }

    private function officeReadable(string $directory, string $input): void
    {
        mkdir($directory . '/profile/user', 0700, true);
        $settings = '<?xml version="1.0"?><oor:items xmlns:oor="http://openoffice.org/2001/registry">';
        $settings .= '<item oor:path="/org.openoffice.Office.Common/Security/Scripting"><prop oor:name="MacroSecurityLevel" oor:op="fuse"><value>3</value></prop></item>';
        foreach (['Writer', 'Calc'] as $app) {
            $settings .= '<item oor:path="/org.openoffice.Office.' . $app . '/Content/Update"><prop oor:name="Link" oor:op="fuse"><value>2</value></prop></item>';
        }
        file_put_contents($directory . '/profile/user/registrymodifications.xcu', $settings . '</oor:items>');
        $this->run(['libreoffice', '-env:UserInstallation=file://' . $directory . '/profile', '--headless', '--nologo', '--nodefault', '--norestore', '--convert-to', 'pdf', '--outdir', $directory, $input]);
        if (!is_file($directory . '/document.pdf') || filesize($directory . '/document.pdf') === 0) {
            throw new RuntimeException('Không mở được file Office: file lỗi, đặt mật khẩu hoặc không được bộ đọc hỗ trợ.');
        }
        // Check the parser output too; the original upload is never replaced by it.
        $this->pdf($directory, $directory . '/document.pdf');
    }

    private function pdf(string $directory, string $input): array
    {
        $this->run(['qpdf', '--check', $input]);
        $info = $this->run(['pdfinfo', $input]);
        if (preg_match('/Encrypted:\s+yes/i', $info)) {
            throw new RuntimeException('PDF có mã hóa/đặt mật khẩu nên không xác minh đầy đủ được.');
        }
        preg_match('/^Pages:\s*(\d+)/mi', $info, $pages);
        $pageCount = (int) ($pages[1] ?? 0);
        if ($pageCount < 1 || $pageCount > 2000) {
            throw new RuntimeException('Không xác minh được số trang PDF hoặc vượt ngân sách kiểm tra 2.000 trang.');
        }
        $this->run(['pdftoppm', '-scale-to', '128', '-png', $input, $directory . '/preview']);
        if (count(glob($directory . '/preview-*.png')) !== $pageCount) {
            throw new RuntimeException('Không dựng được đầy đủ các trang PDF.');
        }
        preg_match('/PDF version:\s*([\d.]+)/i', $info, $match);
        return ['PDF phiên bản ' . ($match[1] ?? 'chưa xác định') . '. Đã kiểm tra cấu trúc và dựng thử ' . $pageCount . ' trang bằng Poppler; chưa kiểm chứng độ chính xác hiển thị và phần mềm đọc PDF phiên bản cũ.'];
    }

    private function run(array $command): string
    {
        $process = new Process($command);
        $process->setTimeout(60);
        try {
            $process->run();
        } catch (\Symfony\Component\Process\Exception\ProcessTimedOutException $error) {
            throw new RuntimeException('Quá thời gian kiểm tra 60 giây, chưa xác minh được file.');
        }
        if (!$process->isSuccessful()) {
            if ($process->getExitCode() === 127) {
                throw new RuntimeException('Máy chủ chưa có công cụ kiểm tra file; chưa thể tiếp nhận.');
            }
            if (preg_match('/password|encrypted/i', $process->getErrorOutput())) {
                throw new RuntimeException('File có mã hóa/đặt mật khẩu; không xác minh đầy đủ được.');
            }
            throw new RuntimeException($command[0] === 'qpdf'
                ? 'PDF bị lỗi cấu trúc hoặc có cảnh báo cần sửa; hãy mở và lưu lại file rồi thử lại.'
                : 'Bộ đọc không mở được file: file lỗi hoặc có tính năng không được hỗ trợ.');
        }
        if ($command[0] === 'pdftoppm' && preg_match('/Syntax Error|Error:/i', $process->getErrorOutput())) {
            throw new RuntimeException('PDF có lỗi khi dựng nội dung trang; hãy mở và lưu lại file rồi thử lại.');
        }
        return $process->getOutput();
    }
}
