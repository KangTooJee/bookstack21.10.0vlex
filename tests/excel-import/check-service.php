<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error) { fwrite(STDERR, $error . "\n"); exit(1); });
function ensure($condition, string $message): void { if (!$condition) { throw new RuntimeException($message); } }
$directory = __DIR__;
$importer = $app->make(BookStack\Uploads\ExcelImportService::class);
$exporter = $app->make(BookStack\Uploads\ExcelExportService::class);
config(['app.allow_content_scripts' => true]);
$image = imagecreatetruecolor(24, 24);
imagefill($image, 0, 0, imagecolorallocate($image, 20, 90, 180));
ob_start(); imagepng($image); $png = ob_get_clean(); imagedestroy($image);
$page = new BookStack\Entities\Models\Page();
$page->name = 'Bảng tính tiếng Việt';
$page->html = '<h2>Dữ liệu đã sửa trên web</h2><table><tr><th colspan="2" style="background-color:#FFFF00;font-family:Arial;font-size:16pt;border:2px solid #ff0000">Ô gộp</th></tr>' .
    '<tr><td style="font-weight:bold;color:#ff0000">Mã hàng</td><td>00123</td></tr>' .
    '<tr><td>Số tiền</td><td>1234.5</td></tr><tr><td>Ngày</td><td>26/10/2026</td></tr>' .
    '<tr><td>=1+2</td><td>+CMD</td></tr><tr><td>@SUM(1,2)</td><td>-TEXT</td></tr>' .
    '<tr><td>Ảnh nhúng</td><td><img src="data:image/png;base64,' . base64_encode($png) . '" width="24" height="24"></td></tr>' .
    '<tr><td rowspan="2">Ô dọc</td><td><strong>Bold run</strong> and <em>Italic run</em></td></tr><tr><td style="border-left:none;border-right:3px dashed #00ff00">Dưới</td></tr></table>' .
    '<h2>Sheet thứ hai</h2><table><tr><td>Sheet hai</td><td>GIỮ NỘI DUNG</td></tr></table>' .
    '<p><a name="empty-bookmark"></a><strong>Rich &amp; text</strong><span></span> after bookmark <a name="empty-end"></a></p>' .
    '<script>EVIL_SCRIPT</script><p onclick="EVIL_EVENT()" style="background-image:url(http://invalid.example/track)">KẾT THÚC</p>';
$original = $page->html;
$before = glob('/tmp/bookstack-excel-*');
$bytes = $exporter->export($page);
ensure($page->html === $original, 'Export changed saved HTML');
file_put_contents($directory . '/fixture.xlsx', $bytes);
$zip = new ZipArchive(); ensure($zip->open($directory . '/fixture.xlsx') === true, 'Not XLSX');
$strings = $zip->getFromName('xl/worksheets/sheet1.xml');
$doc = new DOMDocument(); $doc->loadXML($strings);
$xpath = new DOMXPath($doc); $xpath->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
// Excel rejects this CT_Rst schema violation even though the XML parses and Calc tolerates it.
ensure($xpath->query('//s:is[s:t and s:r] | //s:is[count(s:t)>1] | //s:is[not(s:t) and not(s:r)] | //s:r[count(s:t)!=1]')->length === 0, 'Invalid inline-string structure triggers Excel repair');
ensure(strpos($doc->textContent, 'Rich & text after bookmark') !== false, 'Empty anchors lost surrounding rich text');
foreach (['Bảng tính tiếng Việt', 'Dữ liệu đã sửa trên web', 'Ô gộp', '00123', '26/10/2026', '=1+2', 'GIỮ NỘI DUNG', 'KẾT THÚC'] as $expected) {
    ensure(strpos($doc->textContent, $expected) !== false, 'Export lost text/type: ' . $expected);
}
ensure(strpos($strings, 'EVIL_') === false, 'Active HTML survived');
$sheets = ''; $media = 0;
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = $zip->getNameIndex($i);
    if (preg_match('~^xl/worksheets/sheet\d+\.xml$~', $name)) { $sheets .= $zip->getFromIndex($i); }
    if (strpos($name, 'xl/media/') === 0) { $media++; }
}
ensure(strpos($sheets, '<f') === false, 'Literal web text became an Excel formula');
ensure(strpos($sheets, 'mergeCell') !== false, 'Merged cell lost');
ensure(strpos($sheets, 'A10:A11') !== false && strpos($sheets, 'rFont') !== false && strpos($sheets, '<i/>') !== false, 'Vertical merge or rich text lost');
ensure($media >= 1, 'Image not embedded');
$styles = $zip->getFromName('xl/styles.xml');
ensure(stripos($styles, 'FFFF00') !== false && stripos($styles, 'FF0000') !== false && strpos($styles, 'Arial') !== false, 'Cell colors/font lost');
ensure(strpos($styles, 'style="dashed"') !== false && strpos($styles, 'FF00FF00') !== false, 'Per-edge cell border lost');
$zip->close();
echo "XLSX export: saved text, leading zeros, literal formulas, merged cells, colors, fonts, image and sanitized HTML PASS\n";
$html = $importer->convert(new Symfony\Component\HttpFoundation\File\UploadedFile($directory . '/fixture.xlsx', 'fixture.xlsx', null, null, true));
file_put_contents($directory . '/imported.html', $html);
foreach (['Ô gộp', '00123', '1234.5', 'Sheet hai', 'GIỮ NỘI DUNG', 'data:image/', 'colspan="2"', 'rowspan="2"', 'background-color: #FFFF00'] as $expected) {
    ensure(strpos($html, $expected) !== false, 'Import lost: ' . $expected);
}
ensure(strpos($html, 'sdformula') === false && strpos($html, '<script') === false, 'Formula/active HTML survived import');
echo "XLSX import: text, table, merged cells, formatted values and embedded image PASS\n";
// Create a real two-sheet OOXML workbook with a cached formula result.
$zip = new ZipArchive(); $zip->open($directory . '/multi.xlsx', ZipArchive::CREATE | ZipArchive::OVERWRITE);
$zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
$zip->addFromString('_rels/.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
$zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Danh sách" sheetId="1" r:id="rId1"/><sheet name="Tổng hợp" sheetId="2" r:id="rId2"/></sheets></workbook>');
$zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/></Relationships>');
foreach ([1 => 'CHI TIẾT SHEET MỘT', 2 => 'DỮ LIỆU SHEET HAI'] as $id => $value) {
    $zip->addFromString('xl/worksheets/sheet' . $id . '.xml', '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>' . $value . '</t></is></c><c r="B1"><f>1+2</f><v>3</v></c></row></sheetData></worksheet>');
}
$zip->close();
$hash = hash_file('sha256', $directory . '/multi.xlsx');
$html = $importer->convert(new Symfony\Component\HttpFoundation\File\UploadedFile($directory . '/multi.xlsx', 'multi.xlsx', null, null, true));
file_put_contents($directory . '/multi-imported.html', $html);
foreach (['CHI TIẾT SHEET MỘT', 'DỮ LIỆU SHEET HAI', 'Danh sách', 'Tổng hợp'] as $expected) {
    ensure(strpos($html, $expected) !== false, 'Multiple-sheet import lost ' . $expected);
}
ensure(substr_count($html, '<table') === 2 && strpos(strip_tags($html), '3') !== false, 'Formula value or sheet table lost');
ensure($hash === hash_file('sha256', $directory . '/multi.xlsx'), 'Original workbook modified');
echo "Multi-sheet import: both sheet names/tables, formula values and original file preserved PASS\n";
// Real legacy XLS is generated by Calc, then tested through the same importer.
$process = new Symfony\Component\Process\Process(['libreoffice', '-env:UserInstallation=file://' . $directory . '/fixture-profile', '--headless', '--convert-to', 'xls:MS Excel 97', '--outdir', $directory, $directory . '/multi.xlsx']);
$process->setTimeout(60); $process->mustRun();
$html = $importer->convert(new Symfony\Component\HttpFoundation\File\UploadedFile($directory . '/multi.xls', 'multi.xls', null, null, true));
ensure(strpos($html, 'DỮ LIỆU SHEET HAI') !== false, 'XLS import lost second sheet');
echo "Legacy XLS import PASS\n";
foreach (['invalid.xls', 'invalid.xlsx', 'invalid.csv'] as $name) {
    file_put_contents($directory . '/' . $name, 'invalid spreadsheet');
    $failed = false;
    try { $importer->convert(new Symfony\Component\HttpFoundation\File\UploadedFile($directory . '/' . $name, $name, null, null, true)); }
    catch (RuntimeException $error) { $failed = true; }
    ensure($failed, 'Invalid format accepted ' . $name);
}
$zip = new ZipArchive(); $zip->open($directory . '/macro.xlsx', ZipArchive::CREATE | ZipArchive::OVERWRITE);
$zip->addFromString('xl/workbook.xml', '<workbook/>'); $zip->addFromString('xl/vbaProject.bin', 'macro'); $zip->close();
$failed = false;
try { $importer->convert(new Symfony\Component\HttpFoundation\File\UploadedFile($directory . '/macro.xlsx', 'macro.xlsx', null, null, true)); }
catch (RuntimeException $error) { $failed = true; }
ensure($failed, 'Macro workbook accepted');
foreach (['<table><tr><td colspan="257">Too wide</td></tr></table>', '<p>' . str_repeat('x', 32768) . '</p>'] as $badHtml) {
    $page->html = $badHtml; $failed = false;
    try { $exporter->export($page); } catch (RuntimeException $error) { $failed = true; }
    ensure($failed, 'Excel limits were not enforced');
}
$page->html = '<p>Unsafe image</p><img src="file:///etc/passwd">';
$failed = false;
try { $exporter->export($page); } catch (RuntimeException $error) { $failed = true; }
ensure($failed, 'Local file image accepted');
ensure(glob('/tmp/bookstack-excel-*') === $before, 'Temporary conversion directories leaked');
echo "Invalid files, unsafe images and temporary cleanup PASS\n";
