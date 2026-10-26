<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error) { fwrite(STDERR, $error->getMessage() . "\n"); exit(1); });
$importer = $app->make(BookStack\Uploads\WordImportService::class);
$exporter = $app->make(BookStack\Uploads\WordExportService::class);
// In-memory importer output has data URIs; saved pages use uploaded image URLs.
// Also verify the exporter sanitizes resources even when page scripts are enabled.
config(['app.allow_content_scripts' => true]);
$directory = '/tmp/word-import-test';
$page = new BookStack\Entities\Models\Page();
$page->name = 'Kiểm tra xuất Word';
$page->html = $importer->convert(new Symfony\Component\HttpFoundation\File\UploadedFile($directory . '/fixture.docx', 'fixture.docx', null, null, true));
$page->html .= '<h2>Đã chỉnh sửa trên web</h2><p>Nội dung mới <strong>in đậm</strong> <em>in nghiêng</em>.</p>';
$page->html .= $importer->convert(new Symfony\Component\HttpFoundation\File\UploadedFile($directory . '/layout.docx', 'layout.docx', null, null, true));
$before = glob(sys_get_temp_dir() . '/bookstack-word-export-*');
$bytes = $exporter->export($page);
file_put_contents($directory . '/exported.docx', $bytes);
$zip = new ZipArchive();
if ($zip->open($directory . '/exported.docx') !== true) { throw new RuntimeException('Output is not a DOCX'); }
$document = new DOMDocument(); $document->loadXML($zip->getFromName('word/document.xml'));
$ns = new DOMXPath($document); $ns->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
foreach (['Kiểm tra xuất Word', 'Tiêu đề tiếng Việt', 'Đã chỉnh sửa trên web', 'Nội dung mới', 'Dữ liệu 123', 'Ô gộp', 'Root bullet', 'Nested bullet'] as $expected) {
    if (strpos($document->textContent, $expected) === false) { throw new RuntimeException('Missing exported content: ' . $expected); }
}
if ($ns->query('//w:tbl')->length !== 1 || !$ns->query('//w:gridSpan[@w:val="2"]')->length) { throw new RuntimeException('Table/merged cell lost'); }
$media = 0;
for ($i=0; $i<$zip->numFiles; $i++) { if (strpos($zip->getNameIndex($i), 'word/media/') === 0) { $media++; } }
if ($media !== 2) { throw new RuntimeException('Expected two embedded images'); }
$ns->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
$ns->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
if ($ns->query('//a:blip[@r:embed]')->length !== 2 || $ns->query('//a:blip[@r:link]')->length !== 0) { throw new RuntimeException('Images still use linked drawing references'); }
if (!$ns->query('//w:hyperlink[@w:anchor="word-heading"]')->length || !$ns->query('//w:bookmarkStart[@w:name="word-heading"]')->length) { throw new RuntimeException('TOC link/bookmark lost'); }
if (glob(sys_get_temp_dir() . '/bookstack-word-export-*') !== $before) { throw new RuntimeException('Temporary files leaked'); }
$zip->close();
$roundtrip = $importer->convert(new Symfony\Component\HttpFoundation\File\UploadedFile($directory . '/exported.docx', 'exported.docx', null, null, true));
file_put_contents($directory . '/export-roundtrip.html', $roundtrip);
foreach (['margin-left: 1.5in', 'margin-left: 0.5in', 'margin-left: 2.5in', 'margin-left: 1.25in'] as $expected) {
    if (strpos($roundtrip, $expected) === false) { throw new RuntimeException('Round-trip list indent lost: ' . $expected); }
}
echo "DOCX export: edited text, headings, table/merged cell, two embedded images, list indents and cleanup PASS\n";
// Failed export must also clean its working directory; arbitrary URLs are never passed to Writer.
$page->html = '<p>Unavailable image</p><img src="file:///etc/passwd">';
$failed = false;
try { $exporter->export($page); } catch (RuntimeException $error) { $failed = true; }
if (!$failed || glob(sys_get_temp_dir() . '/bookstack-word-export-*') !== $before) { throw new RuntimeException('Unsafe image rejection/cleanup failed'); }
echo "External/file image references rejected and temporary files cleaned PASS\n";
