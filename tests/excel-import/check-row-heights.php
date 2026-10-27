<?php
require '/var/www/html/vendor/autoload.php';

function check($condition, string $message): void { if (!$condition) { throw new RuntimeException($message); } }
$path = tempnam(sys_get_temp_dir(), 'excel-height-');
try {
    $bitmap = imagecreatetruecolor(24, 24);
    ob_start(); imagepng($bitmap); $png = ob_get_clean(); imagedestroy($bitmap);
    $html = '<table><tr height="999" style="height:999px"><td colspan="3">' . str_repeat('Long merged text ', 100) . '</td></tr>' .
        '<tr><td>Short</td><td>Line one<br>Line two</td></tr>' .
        '<tr><td rowspan="2">Vertical merge</td><td>Top</td></tr><tr><td>Bottom</td></tr>' .
        '<tr><td><img src="data:image/png;base64,' . base64_encode($png) . '" width="24"></td></tr></table>';
    $writer = new BookStack\Uploads\ExcelWorkbookWriter();
    $writer->write($html, 'Title', $path);
    $zip = new ZipArchive(); check($zip->open($path) === true, 'Invalid XLSX');
    $doc = new DOMDocument(); check($doc->loadXML($zip->getFromName('xl/worksheets/sheet1.xml')), 'Invalid worksheet XML');
    $xp = new DOMXPath($doc); $xp->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    check($xp->query('//s:row')->length === 6, 'Rows lost');
    check($xp->query('//s:row[@r < 6][@ht or @customHeight]')->length === 0, 'Text/merged rows have fixed heights');
    check($xp->query('//s:row[@r="6"][@ht="38" and @customHeight="1"]')->length === 1, 'Image space lost');
    check($xp->query('//s:mergeCell')->length === 2, 'Merges lost');
    check($xp->query('//s:is[s:t and s:r]')->length === 0, 'Invalid inline strings');
    $styles = $zip->getFromName('xl/styles.xml');
    check(strpos($styles, 'wrapText="1"') !== false, 'Text wrapping lost');
    $zip->close();
    // Reusing the writer must not leave image heights on text-only exports.
    $writer->write('<table><tr><td>Text only</td></tr></table>', 'Title', $path);
    $zip->open($path); $xml = $zip->getFromName('xl/worksheets/sheet1.xml'); $zip->close();
    check(strpos($xml, 'customHeight') === false && strpos($xml, ' ht=') === false, 'Image heights leaked to next export');
    echo "XLSX row heights: text/merged rows automatic; source fixed heights ignored; wrap retained; image space reserved; writer reuse PASS\n";
} finally {
    if (is_file($path)) { unlink($path); }
}
