<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error) { fwrite(STDERR, $error . "\n"); exit(1); });
// One bounded run, independent of LibreOffice. Do not retry a real failing workbook.
set_time_limit(15);
$row = '<tr>' . str_repeat('<td bgcolor="#ffffff"><font color="#000000">Ô tiếng Việt</font></td>', 10) . '</tr>';
$html = '<body><h1>Sheet một</h1><table>' . str_repeat($row, 1000) . '</table><h1>Sheet hai</h1><table>' . str_repeat($row, 1000) . '</table></body>';
$start = microtime(true);
$service = new BookStack\Uploads\WordImportService();
$clean = $service->cleanHtml($html, '/tmp', [], 100000);
$elapsed = microtime(true) - $start;
if (substr_count($clean, '<td') !== 20000 || substr_count($clean, 'Ô tiếng Việt') !== 20000) {
    throw new RuntimeException('Bounded HTML cleanup lost cells');
}
printf("Two sheet HTML / 20,000 cells / %d bytes: %.3fs, peak %.1f MiB PASS\n", strlen($html), $elapsed, memory_get_peak_usage(true) / 1048576);
$failed = false;
try { $service->cleanHtml('<body><table>' . $row . '</table></body>', '/tmp', [], 10); }
catch (RuntimeException $error) { $failed = true; }
if (!$failed) { throw new RuntimeException('Element budget not enforced'); }
$legacy = '<table id="bkmrk-table" border="0" cellspacing="0"><colgroup width="120"></colgroup><tr><td>Excel cũ</td></tr></table>';
if (!BookStack\Uploads\ExcelPageLayout::matches($legacy)
    || !BookStack\Uploads\ExcelPageLayout::matches('<a name="bookstack-excel-content"></a><table></table>')
    || BookStack\Uploads\ExcelPageLayout::matches('<p>Word</p><table><tr><td>ordinary</td></tr></table>')) {
    throw new RuntimeException('Excel page layout classification failed');
}
echo "Element budget and old/new Excel page layout classification PASS\n";
