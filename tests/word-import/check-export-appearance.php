<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error) { fwrite(STDERR, $error->getMessage() . "\n"); exit(1); });
$exporter = $app->make(BookStack\Uploads\WordExportService::class);
$page = new BookStack\Entities\Models\Page(); $page->name = 'Font and border fidelity';
$page->html = '<p>DEFAULT-FONT</p><h2>DEFAULT-HEADING</h2>' .
    '<div style="font-family:Times New Roman; font-size:18pt"><p>TIMES-INHERITED</p><p><span style="font-family:Arial;font-size:24px">ARIAL-PIXELS</span> <span style="font-size:150%">RELATIVE-SIZE</span> <span style="font-size:0.5em">HALF-SIZE</span></p></div>' .
    '<p><font face="Georgia" size="5">LEGACY-GEORGIA</font> <span style="font-size:large">KEYWORD-LARGE</span> <span style="font-size:1.5rem">ROOT-RELATIVE</span></p>' .
    '<table style="width:100%"><tr><th>DEFAULT-HEADER</th><th>DEFAULT-HEADER-TWO</th></tr><tr><td>DEFAULT-CELL</td><td>DEFAULT-CELL-TWO</td></tr></table>' .
    '<table style="width:100%;font-family:Courier New;font-size:16px;border:2px solid #123456"><tr>' .
    '<td style="border:1.5pt dashed red">COURIER-CELL</td><td style="border:none"><span style="font-family:Times New Roman;font-size:20pt">CELL-OVERRIDE</span></td>' .
    '<td style="border-width:1px 2px 3px 4px;border-style:solid;border-color:#abc; border-bottom:3px double rgb(0, 128, 255)">PER-EDGE-BORDER</td></tr></table>' .
    '<table border="0" style="width:100%;border:none"><tr><td style="border:0px solid black">BORDERLESS</td></tr></table>';
$original = $page->html;
$bytes = $exporter->export($page);
if ($page->html !== $original) { throw new RuntimeException('Export changed saved HTML'); }
$path = '/tmp/word-import-test/export-appearance.docx'; file_put_contents($path, $bytes);
$zip = new ZipArchive(); $zip->open($path);
$d = new DOMDocument(); $d->loadXML($zip->getFromName('word/document.xml')); $zip->close();
$x = new DOMXPath($d); $w = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'; $x->registerNamespace('w', $w);
foreach (['Font and border fidelity' => ['Segoe UI',72], 'DEFAULT-FONT' => ['Segoe UI',21], 'DEFAULT-HEADING' => ['Segoe UI',59], 'TIMES-INHERITED' => ['Times New Roman',36], 'ARIAL-PIXELS' => ['Arial',36], 'RELATIVE-SIZE' => ['Times New Roman',54], 'HALF-SIZE' => ['Times New Roman',18], 'LEGACY-GEORGIA' => ['Georgia',36], 'KEYWORD-LARGE' => ['Segoe UI',27], 'ROOT-RELATIVE' => ['Segoe UI',36], 'COURIER-CELL' => ['Courier New',24], 'CELL-OVERRIDE' => ['Times New Roman',40]] as $text => [$family,$size]) {
    $run = $x->query('//w:r[w:t="' . $text . '"]')->item(0);
    if (!$run) { throw new RuntimeException('Missing font test text: ' . $text); }
    $actualFamily = $x->evaluate('string(w:rPr/w:rFonts/@w:ascii)', $run);
    $actualSize = (int) $x->evaluate('string(w:rPr/w:sz/@w:val)', $run);
    if ($actualFamily !== $family || $actualSize !== $size) { throw new RuntimeException('Incorrect font for ' . $text . ': ' . $actualFamily . '/' . $actualSize . ' expected ' . $family . '/' . $size); }
}
$checkBorder = function ($path, $style, $size = null, $color = null) use ($x, $w) {
    $border = $x->query($path)->item(0);
    if (!$border || $border->getAttributeNS($w, 'val') !== $style || ($size !== null && (int) $border->getAttributeNS($w, 'sz') !== $size) || ($color !== null && $border->getAttributeNS($w, 'color') !== $color)) { throw new RuntimeException('Border mismatch: ' . $path); }
};
$checkBorder('//w:tc[w:p/w:r/w:t="DEFAULT-CELL"]/w:tcPr/w:tcBorders/w:top', 'single', 6, 'DDDDDD');
$checkBorder('//w:tbl[2]/w:tblPr/w:tblBorders/w:top', 'single', 12, '123456');
$checkBorder('//w:tc[w:p/w:r/w:t="COURIER-CELL"]/w:tcPr/w:tcBorders/w:right', 'dashed', 12, 'FF0000');
$checkBorder('//w:tc[w:p/w:r/w:t="COURIER-CELL"]/w:tcPr/w:tcBorders/w:top', 'single', 12, '123456');
$checkBorder('//w:tc[w:p/w:r/w:t="CELL-OVERRIDE"]/w:tcPr/w:tcBorders/w:right', 'nil');
$checkBorder('//w:tc[w:p/w:r/w:t="CELL-OVERRIDE"]/w:tcPr/w:tcBorders/w:top', 'single', 12, '123456');
$checkBorder('//w:tc[w:p/w:r/w:t="PER-EDGE-BORDER"]/w:tcPr/w:tcBorders/w:left', 'single', 24, 'AABBCC');
$checkBorder('//w:tc[w:p/w:r/w:t="PER-EDGE-BORDER"]/w:tcPr/w:tcBorders/w:bottom', 'double', 18, '0080FF');
$checkBorder('//w:tc[w:p/w:r/w:t="BORDERLESS"]/w:tcPr/w:tcBorders/w:top', 'nil');
$checkBorder('//w:tbl[3]/w:tblPr/w:tblBorders/w:top', 'nil');
echo "Appearance: inherited/overridden font families, px/pt/em/percent/keywords, legacy fonts, visible/default/custom/per-edge borders and borderless tables PASS\n";
