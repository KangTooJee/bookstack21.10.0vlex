<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error) { fwrite(STDERR, $error . "\n"); exit(1); });
use BookStack\Uploads\ExcelImportPolicy;
use BookStack\Uploads\ExcelImportService;
use Symfony\Component\HttpFoundation\File\UploadedFile;

function workbook(string $path, int $count): void {
    $zip = new ZipArchive(); $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $rels = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    $package = 'http://schemas.openxmlformats.org/package/2006/relationships';
    $types = '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
    $sheets = '<workbook xmlns="' . $ns . '" xmlns:r="' . $rels . '"><sheets>';
    $links = '<Relationships xmlns="' . $package . '">';
    for ($id = 1; $id <= $count; $id++) {
        $sheets .= '<sheet name="Sheet ' . $id . '" sheetId="' . $id . '" r:id="sheet' . $id . '"' . ($id === $count ? ' state="hidden"' : '') . '/>';
        $links .= '<Relationship Id="sheet' . $id . '" Type="' . $rels . '/worksheet" Target="worksheets/sheet' . $id . '.xml"/>';
        $types .= '<Override PartName="/xl/worksheets/sheet' . $id . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $zip->addFromString('xl/worksheets/sheet' . $id . '.xml', '<worksheet xmlns="' . $ns . '"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>Sheet ' . $id . '</t></is></c></row></sheetData></worksheet>');
    }
    $zip->addFromString('[Content_Types].xml', $types . '</Types>');
    $zip->addFromString('_rels/.rels', '<Relationships xmlns="' . $package . '"><Relationship Id="workbook" Type="' . $rels . '/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml', $sheets . '</sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', $links . '</Relationships>');
    $zip->close();
}
function reject(string $path, string $expected): void {
    $before = glob('/tmp/bookstack-excel-import-*');
    $failed = false;
    try { (new ExcelImportService())->convert(new UploadedFile($path, basename($path), null, null, true)); }
    catch (RuntimeException $error) {
        if (strpos($error->getMessage(), $expected) === false) { throw $error; }
        $failed = true;
    }
    if (!$failed || glob('/tmp/bookstack-excel-import-*') !== $before) { throw new RuntimeException('Import was not rejected before temporary conversion: ' . basename($path)); }
}
$policy = new ExcelImportPolicy();
workbook(__DIR__ . '/five-sheets.xlsx', 5);
workbook(__DIR__ . '/six-sheets.xlsx', 6);
$policy->validate(new UploadedFile(__DIR__ . '/five-sheets.xlsx', 'five.xlsx', null, null, true));
$policy->validate(new UploadedFile(__DIR__ . '/fixture.xlsx', 'fixture.xlsx', null, null, true));
$policy->validate(new UploadedFile(__DIR__ . '/multi.xls', 'multi.xls', null, null, true));
reject(__DIR__ . '/six-sheets.xlsx', '6 sheet');
// Generate a tiny real BIFF XLS containing six tabs (including a hidden one).
$process = new Symfony\Component\Process\Process(['libreoffice', '-env:UserInstallation=file://' . __DIR__ . '/preflight-profile', '--headless', '--convert-to', 'xls:MS Excel 97', '--outdir', __DIR__, __DIR__ . '/six-sheets.xlsx']);
$process->setTimeout(15); $process->mustRun();
reject(__DIR__ . '/six-sheets.xls', '6 sheet');
$handle = fopen(__DIR__ . '/oversize.xlsx', 'wb'); ftruncate($handle, ExcelImportPolicy::MAX_KB * 1024 + 1); fclose($handle);
reject(__DIR__ . '/oversize.xlsx', '2048 KB');
echo "Preflight: <=5 sheets allowed; 6 tabs including hidden rejected for XLSX/XLS; >2048 KiB rejected; no conversion directory created PASS\n";
