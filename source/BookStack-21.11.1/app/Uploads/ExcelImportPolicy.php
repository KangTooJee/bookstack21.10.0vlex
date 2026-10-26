<?php

namespace BookStack\Uploads;

use DOMDocument;
use DOMXPath;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Read only workbook metadata before any temporary conversion or Calc process. */
class ExcelImportPolicy
{
    public const MAX_KB = 2048;
    public const MAX_SHEETS = 5;

    public function validate(UploadedFile $upload): void
    {
        $extension = strtolower($upload->getClientOriginalExtension());
        if (!in_array($extension, ['xls', 'xlsx'], true)) {
            throw new RuntimeException('Chỉ hỗ trợ bảng tính .xls và .xlsx.');
        }
        if ($upload->getSize() > self::MAX_KB * 1024) {
            throw new RuntimeException('File Excel vượt quá ' . self::MAX_KB . ' KB. Hãy tách sheet/vùng dữ liệu thành file nhỏ hơn.');
        }
        $sheets = $extension === 'xlsx' ? $this->xlsxSheets($upload->getRealPath()) : $this->xlsSheets($upload->getRealPath());
        if ($sheets < 1) {
            throw new RuntimeException('Không đọc được danh sách sheet của Excel. Hãy lưu lại file rồi thử lại.');
        }
        if ($sheets > self::MAX_SHEETS) {
            throw new RuntimeException('File Excel có ' . $sheets . ' sheet, vượt giới hạn ' . self::MAX_SHEETS . ' sheet (tính cả sheet ẩn). Hãy tách file trước khi nhập.');
        }
    }

    private function xlsxSheets(string $path): int
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('File .xlsx không hợp lệ hoặc đã hỏng.');
        }
        try {
            $expanded = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $expanded += $zip->statIndex($index)['size'];
            }
            $metadata = $zip->statName('xl/workbook.xml');
            if (!$metadata || $metadata['size'] > 1024 * 1024 || $zip->numFiles > 5000 || $expanded > 100 * 1024 * 1024) {
                throw new RuntimeException('Bảng tính .xlsx không hợp lệ hoặc quá lớn sau giải nén.');
            }
            if ($zip->locateName('xl/vbaProject.bin') !== false) {
                throw new RuntimeException('Không hỗ trợ bảng tính có macro. Hãy lưu bản .xlsx không có macro.');
            }
            $xml = $zip->getFromName('xl/workbook.xml');
            if ($xml === false || stripos($xml, '<!DOCTYPE') !== false) {
                throw new RuntimeException('Không đọc được cấu trúc workbook .xlsx.');
            }
            $doc = new DOMDocument();
            $previous = libxml_use_internal_errors(true);
            try {
                if (!$doc->loadXML($xml, LIBXML_NONET)) {
                    throw new RuntimeException('Cấu trúc workbook .xlsx bị lỗi.');
                }
            } finally {
                libxml_clear_errors(); libxml_use_internal_errors($previous);
            }
            $xpath = new DOMXPath($doc);
            $xpath->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $xpath->registerNamespace('strict', 'http://purl.oclc.org/ooxml/spreadsheetml/main');
            return $xpath->query('/s:workbook/s:sheets/s:sheet | /strict:workbook/strict:sheets/strict:sheet')->length;
        } finally {
            $zip->close();
        }
    }

    private function xlsSheets(string $path): int
    {
        $file = file_get_contents($path);
        if (strlen($file) < 512 || substr($file, 0, 8) !== hex2bin('d0cf11e0a1b11ae1')) {
            throw new RuntimeException('File .xls không đúng định dạng Excel 97–2003. Hãy lưu lại thành .xlsx.');
        }
        $invalid = 'Không đọc được danh sách sheet .xls. Hãy lưu file thành .xlsx rồi nhập lại.';
        $u16 = static fn(string $bytes, int $offset): int => unpack('v', substr($bytes, $offset, 2))[1];
        $u32 = static fn(string $bytes, int $offset): int => unpack('V', substr($bytes, $offset, 4))[1];
        $version = $u16($file, 26);
        $shift = $u16($file, 30);
        if ($u16($file, 28) !== 0xfffe || !in_array([$version, $shift], [[3, 9], [4, 12]], true)
            || $u16($file, 32) !== 6 || $u32($file, 56) !== 4096) { throw new RuntimeException($invalid); }
        $sectorSize = 1 << $shift;
        $sector = static function (int $id) use ($file, $sectorSize, $invalid): string {
            $offset = ($id + 1) * $sectorSize;
            if ($id < 0 || $id >= 0xfffffffa || $offset + $sectorSize > strlen($file)) { throw new RuntimeException($invalid); }
            return substr($file, $offset, $sectorSize);
        };
        // With the 2 MiB input cap the header's 109 FAT entries are sufficient.
        $fatCount = $u32($file, 44);
        if ($fatCount < 1 || $fatCount > 109 || $u32($file, 72) !== 0) { throw new RuntimeException($invalid); }
        $fat = [];
        $fatIds = [];
        for ($i = 0; $i < $fatCount; $i++) {
            $id = $u32($file, 76 + 4 * $i);
            if (isset($fatIds[$id])) { throw new RuntimeException($invalid); }
            $fatIds[$id] = true;
            $fat = array_merge($fat, array_values(unpack('V*', $sector($id))));
        }
        $chain = static function (int $id, array $table, callable $read) use ($file, $invalid): string {
            $result = ''; $seen = [];
            while ($id !== 0xfffffffe) {
                if ($id >= 0xfffffffa || isset($seen[$id]) || !isset($table[$id])) { throw new RuntimeException($invalid); }
                $seen[$id] = true;
                $result .= $read($id);
                if (strlen($result) > strlen($file)) { throw new RuntimeException($invalid); }
                $id = $table[$id];
            }
            return $result;
        };
        $directory = $chain($u32($file, 48), $fat, $sector);
        $entries = [];
        for ($offset = 0; $offset + 128 <= strlen($directory); $offset += 128) {
            $entry = substr($directory, $offset, 128);
            $length = $u16($entry, 64);
            if ($length < 2 || $length > 64 || $length % 2 !== 0) { continue; }
            $type = ord($entry[66]);
            $name = mb_convert_encoding(substr($entry, 0, $length - 2), 'UTF-8', 'UTF-16LE');
            if ($u32($entry, 124) !== 0) { throw new RuntimeException($invalid); }
            $entries[intdiv($offset, 128)] = [
                'start'=>$u32($entry, 116), 'size'=>$u32($entry, 120), 'type'=>$type, 'name'=>$name,
                'left'=>$u32($entry, 68), 'right'=>$u32($entry, 72), 'child'=>$u32($entry, 76),
            ];
        }
        $root = $entries[0] ?? null;
        if (!$root || $root['type'] !== 5) { throw new RuntimeException($invalid); }
        // Only root-level streams are the workbook being imported. Embedded OLE
        // objects can contain another Book/Workbook and must not override it.
        $workbook = null; $pending = [$root['child']]; $seen = [];
        while ($pending) {
            $id = array_pop($pending);
            if ($id === 0xffffffff) { continue; }
            if (!isset($entries[$id]) || isset($seen[$id]) || $id === 0) { throw new RuntimeException($invalid); }
            $seen[$id] = true; $entry = $entries[$id];
            $pending[] = $entry['left']; $pending[] = $entry['right'];
            if ($entry['type'] === 2 && in_array(strtolower($entry['name']), ['workbook', 'book'], true)) {
                if ($workbook !== null) { throw new RuntimeException($invalid); }
                $workbook = $entry;
            }
        }
        if (!$workbook || $workbook['size'] < 8 || $workbook['size'] > strlen($file)) { throw new RuntimeException($invalid); }
        if ($workbook['size'] < $u32($file, 56)) {
            if (!$root || $u32($file, 64) < 1) { throw new RuntimeException($invalid); }
            $miniFat = array_values(unpack('V*', $chain($u32($file, 60), $fat, $sector)));
            $miniStream = substr($chain($root['start'], $fat, $sector), 0, $root['size']);
            $miniSector = static function (int $id) use ($miniStream, $invalid): string {
                if (($id + 1) * 64 > strlen($miniStream)) { throw new RuntimeException($invalid); }
                return substr($miniStream, $id * 64, 64);
            };
            $bytes = $chain($workbook['start'], $miniFat, $miniSector);
        } else {
            $bytes = $chain($workbook['start'], $fat, $sector);
        }
        if (strlen($bytes) < $workbook['size']) { throw new RuntimeException($invalid); }
        $bytes = substr($bytes, 0, $workbook['size']);
        // BIFF5/8 workbook globals contain one BOUNDSHEET record per tab, hidden or visible.
        if ($u16($bytes, 0) !== 0x0809 || $u16($bytes, 2) < 4 || $u16($bytes, 6) !== 0x0005) { throw new RuntimeException($invalid); }
        $sheets = 0;
        for ($offset = 0; $offset + 4 <= strlen($bytes);) {
            $record = $u16($bytes, $offset); $length = $u16($bytes, $offset + 2);
            $offset += 4;
            if ($offset + $length > strlen($bytes)) { throw new RuntimeException($invalid); }
            if ($record === 0x002f) { throw new RuntimeException('Không hỗ trợ file Excel đặt mật khẩu.'); }
            if ($record === 0x0085) { $sheets++; }
            if ($record === 0x000a) { return $sheets; }
            $offset += $length;
        }
        throw new RuntimeException($invalid);
    }
}
