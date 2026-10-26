<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error) { fwrite(STDERR, $error->getMessage() . "\n"); exit(1); });
config(['app.allow_content_scripts' => true]);
$dir = '/tmp/word-import-test';
$exporter = $app->make(BookStack\Uploads\WordExportService::class);
$image = imagecreatetruecolor(1800, 900);
imagefill($image, 0, 0, imagecolorallocate($image, 225, 240, 255));
$ink = imagecolorallocate($image, 20, 65, 120);
imagesetthickness($image, 12); imagerectangle($image, 10, 10, 1789, 889, $ink);
imagestring($image, 5, 40, 40, 'LEFT EDGE - IMAGE CONTENT - RIGHT EDGE', $ink);
ob_start(); imagepng($image); $data = base64_encode(ob_get_clean()); imagedestroy($image);
$src = 'data:image/png;base64,' . $data;
$img = '<img src="' . $src . '" style="max-width:100%; height:auto" width="1800" height="900">';
$page = new BookStack\Entities\Models\Page(); $page->name = 'Word layout regression';
$page->html = '<h2>Full width table with large images</h2><table style="width:100%;height:20px"><tbody><tr style="height:20px">' .
    '<td style="width:33.33%;height:20px"><p>CELL-LEFT</p>' . $img . '</td><td style="width:33.33%"><p>CELL-MIDDLE</p>' . $img . '</td><td style="width:33.34%"><p>CELL-RIGHT</p>' . $img . '</td></tr></tbody></table>' .
    '<p>AFTER-FULL-TABLE</p><h2>Nested and merged cells</h2><table width="2400"><colgroup><col width="25%"><col width="75%"></colgroup><tr><th colspan="2">MERGED-HEADER</th></tr>' .
    '<tr><td rowspan="2"><p>ROWSPAN-CELL</p>' . $img . '</td><td><table width="1800"><tr><td>NESTED-LEFT' . $img . '</td><td>NESTED-RIGHT' . $img . '</td></tr></table></td></tr>' .
    '<tr><td>MERGED-END ' . str_repeat('UnbrokenToken', 24) . '</td></tr></table><p>AFTER-NESTED-TABLE</p>' .
    '<h2>Indented image and long code</h2><div style="margin-left:1.5in;width:2000px">' . $img . '</div>' .
    '<pre style="width:2000px;white-space:pre">CODE-BEGIN' . "\n    " . str_repeat('aVeryLongCodeIdentifier', 20) . "\nCODE-END</pre>" .
    '<p style="white-space:nowrap">LONG-URL <a href="https://example.com/' . str_repeat('path', 40) . '">https://example.com/' . str_repeat('path', 40) . '</a></p>';
$tall = imagecreatetruecolor(400, 3000);
imagefill($tall, 0, 0, imagecolorallocate($tall, 230, 250, 230));
imagesetthickness($tall, 8); imagerectangle($tall, 6, 6, 393, 2993, imagecolorallocate($tall, 20, 90, 40));
ob_start(); imagepng($tall); $tallData = base64_encode(ob_get_clean()); imagedestroy($tall);
$page->html .= '<h2>Tall image constrained to a page</h2><table style="width:100%;white-space:nowrap"><tr height="50"><td><img width="100%" style="height:auto" src="data:image/png;base64,' . $tallData . '"></td></tr></table><p>DOCUMENT-END-VISIBLE</p>';
$original = $page->html;
$bytes = $exporter->export($page);
if ($page->html !== $original) { throw new RuntimeException('Saved HTML was modified'); }
file_put_contents($dir . '/export-layout.docx', $bytes);
$zip = new ZipArchive(); $zip->open($dir . '/export-layout.docx');
$d = new DOMDocument(); $d->loadXML($zip->getFromName('word/document.xml')); $zip->close();
$x = new DOMXPath($d); $w = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
$x->registerNamespace('w', $w);
$x->registerNamespace('wp', 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing');
if ($x->query('//w:tbl')->length !== 4 || !$x->query('//w:vMerge')->length || !$x->query('//w:gridSpan[@w:val="2"]')->length) { throw new RuntimeException('Nested/merged table structure lost'); }
$outer = $x->query('//w:tbl[w:tr/w:tc/w:p/w:r/w:t="MERGED-HEADER"]')->item(0);
if (!$outer || $x->query('w:tr[2]/w:tc[2]/w:tbl', $outer)->length !== 1) { throw new RuntimeException('Nested table moved to wrong cell'); }
$columns = $x->query('w:tblGrid/w:gridCol', $outer);
if (abs((int) $columns->item(0)->getAttributeNS($w, 'w') / 9600 - 0.25) > 0.03) { throw new RuntimeException('Explicit 25/75 column proportions lost'); }
if ($x->query('//w:trHeight|//w:tcPr/w:noWrap')->length) { throw new RuntimeException('Clipping row height or nowrap retained'); }
foreach (['CELL-LEFT','CELL-MIDDLE','CELL-RIGHT','AFTER-FULL-TABLE','NESTED-LEFT','NESTED-RIGHT','AFTER-NESTED-TABLE','CODE-BEGIN','CODE-END','DOCUMENT-END-VISIBLE'] as $text) {
    if (strpos($d->textContent, $text) === false) { throw new RuntimeException('Lost layout content: ' . $text); }
}
$verify = function ($block, $available) use (&$verify, $x, $w) {
    foreach ($block->childNodes as $node) {
        if (!$node instanceof DOMElement) { continue; }
        if ($node->localName === 'tbl') {
            $width = (int) $x->evaluate('string(w:tblPr/w:tblW/@w:w)', $node);
            if ($width > $available || $x->evaluate('string(w:tblPr/w:tblLayout/@w:type)', $node) !== 'fixed') { throw new RuntimeException('Table exceeds containing block'); }
            $total = 0; foreach ($x->query('w:tblGrid/w:gridCol', $node) as $col) { $total += (int) $col->getAttributeNS($w, 'w'); }
            if ($total !== $width) { throw new RuntimeException('Grid exceeds table width'); }
            foreach ($x->query('w:tr/w:tc', $node) as $cell) {
                $cellWidth = (int) $x->evaluate('string(w:tcPr/w:tcW/@w:w)', $cell);
                $verify($cell, $cellWidth - 240);
            }
        } elseif ($node->localName === 'p') {
            $left = max((int) $x->evaluate('string(w:pPr/w:ind/@w:left)', $node), (int) $x->evaluate('string(w:pPr/w:ind/@w:start)', $node));
            foreach ($x->query('.//wp:extent', $node) as $size) {
                if ((int) $size->getAttribute('cx') > ($available - $left) * 635 || (int) $size->getAttribute('cy') > 12000 * 635) { throw new RuntimeException('Image exceeds containing cell/page'); }
            }
        } else { $verify($node, $available); }
    }
};
$verify($x->query('//w:body')->item(0), 9602);
echo "Layout: table widths, 3 image columns, nested tables, colspan/rowspan, fixed heights, long text/code, indented images and unchanged saved HTML PASS\n";
