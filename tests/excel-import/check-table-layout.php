<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error) { fwrite(STDERR, $error . "\n"); exit(1); });
use BookStack\Uploads\ExcelHtmlLayout;
function check(bool $condition, string $message): void { if (!$condition) { throw new RuntimeException($message); } }
function parse(string $html): array {
    $doc = new DOMDocument(); libxml_use_internal_errors(true);
    $doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_NONET); libxml_clear_errors();
    return [$doc, new DOMXPath($doc)];
}
$header = $zero = $data = '';
for ($column = 0; $column < 10; $column++) {
    $header .= '<td rowspan="2" height="57">Header ' . $column . '</td>';
    $zero .= '<td>' . ($column === 0 ? '0' : ($column === 2 ? 'Source project baseline' : '&nbsp;')) . '</td>';
    $data .= '<td>Data ' . $column . '</td>';
}
$prefix = '<a name="bookstack-excel-content"></a><table border="0" cellspacing="0"><colgroup span="10" width="100"></colgroup>';
$suffix = '<tr>' . $zero . '</tr><tr>' . $data . '</tr></table>';
$source = $prefix . '<tr>' . $header . '</tr><tr></tr>' . $suffix;
$safe = (new BookStack\Uploads\WordImportService())->cleanHtml($source, '/tmp');
check(strpos($safe, 'span="10"') !== false, 'Column group span lost in sanitizer');
$prepared = ExcelHtmlLayout::prepare('<a name="bookstack-excel-content"></a>' . $safe);
[$doc, $xpath] = parse($prepared);
check($xpath->query('//tr')->length === 4 && $xpath->query('//td')->length === 30, 'Source row/cell count changed');
check($xpath->query('//tr[not(td) and @id]')->length === 1, 'Merged-only row has no TinyMCE preservation ID');
check(strpos($prepared, 'width:1000px') !== false && strpos($prepared, 'table-layout:fixed') !== false, 'Source column grid not fixed');
check($prepared === ExcelHtmlLayout::prepare($prepared), 'Layout normalization is not idempotent');
$legacy = $prefix . '<tr>' . $header . '</tr>' . $suffix;
[$legacyDoc, $legacyXpath] = parse(ExcelHtmlLayout::prepare($legacy));
check($legacyXpath->query('//tr')->length === 4 && $legacyXpath->query('//td')->length === 30, 'Missing covered row was not restored');
check($legacyXpath->query('//tr[td[normalize-space(.)="0"]]/preceding-sibling::tr[1][not(td) and @id]')->length === 1, 'Row zero is not preceded by preserved covered row');
$partial = '<a name="bookstack-excel-content"></a><table><colgroup span="2" width="100"></colgroup><tr><td rowspan="2">Vertical</td><td>Top</td></tr><tr><td>Bottom</td></tr></table>';
[$partialDoc, $partialXpath] = parse(ExcelHtmlLayout::prepare($partial));
check($partialXpath->query('//tr')->length === 2, 'Valid partial rowspan was altered');
$word = '<p>Word</p><table><tr><td>plain</td></tr></table>';
check(ExcelHtmlLayout::prepare($word) === $word, 'Word HTML changed');
[$notesDoc, $notesXpath] = parse('<table><tr><td>Header<comment>Annotation only</comment></td></tr></table>');
ExcelHtmlLayout::moveCommentsToTitles($notesDoc);
check($notesXpath->query('//comment')->length === 0
    && $notesXpath->evaluate('string(//td)') === 'Header'
    && $notesXpath->evaluate('string(//td/@title)') === 'Annotation only', 'Calc note leaked into visible cell text');
echo "Excel grid: covered empty rows preserved/restored without fake cells; 10 columns/widths; partial rowspans; idempotence; Word unchanged PASS\n";
