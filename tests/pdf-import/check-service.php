<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error) { fwrite(STDERR, $error . "\n"); exit(1); });
$directory = '/tmp/pdf-import-test';
@mkdir($directory, 0700, true);
function ensure($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
function fixture($html, $name, $encrypt = false) {
    global $directory;
    $pdf = app('dompdf.wrapper')->loadHTML('<html><meta charset="utf-8"><style>body {font-family: DejaVu Sans}</style><body>' . $html . '</body></html>');
    $pdf->getDomPDF()->render();
    if ($encrypt) { $pdf->getDomPDF()->getCanvas()->get_cpdf()->setEncryption('secret', 'owner', []); }
    file_put_contents($directory . '/' . $name, $pdf->getDomPDF()->output());
}
$image = imagecreatetruecolor(120, 50);
imagefill($image, 0, 0, imagecolorallocate($image, 40, 120, 200));
imagepng($image, $directory . '/fixture.png'); imagedestroy($image);
$uri = 'data:image/png;base64,' . base64_encode(file_get_contents($directory . '/fixture.png'));
fixture('<h1>Tiêu đề tiếng Việt</h1><p>PDF gốc chỉ xem.</p><table border="1" width="100%"><tr><td width="30%">Cột trái</td><td>Cột phải</td></tr><tr><td colspan="2">Ô gộp</td></tr></table><img src="' . $uri . '"><p style="page-break-before:always">TRANG HAI - KẾT THÚC</p>', 'fixture.pdf');
fixture('<img src="' . $uri . '">', 'scan.pdf');
fixture('<p>Text first page</p><p style="page-break-before:always"><img src="' . $uri . '"></p>', 'mixed.pdf');
fixture('<p>Secret content</p>', 'encrypted.pdf', true);
fixture(str_repeat('<p style="page-break-after:always">Page limit</p>', 51), 'large.pdf');
file_put_contents($directory . '/broken.pdf', '%PDF-1.7 invalid');
file_put_contents($directory . '/fake.pdf', '<script>alert(1)</script>');
$importer = app(BookStack\Uploads\PdfImportService::class);
$upload = function ($file, $name = null) use ($directory) {
    return new Symfony\Component\HttpFoundation\File\UploadedFile($directory . '/' . $file, $name ?? $file, null, null, true);
};
foreach (['fixture.pdf', 'scan.pdf', 'mixed.pdf', 'encrypted.pdf', 'large.pdf'] as $file) {
    $hash = hash_file('sha256', $directory . '/' . $file);
    $importer->validate($upload($file));
    ensure($hash === hash_file('sha256', $directory . '/' . $file), 'Original modified');
}
foreach (['broken.pdf', 'fake.pdf'] as $file) {
    $rejected = false;
    try { $importer->validate($upload($file)); } catch (RuntimeException $error) { $rejected = true; }
    ensure($rejected, 'Invalid PDF accepted');
}
$rejected = false;
try { $importer->validate($upload('fixture.pdf', 'wrong.docx')); } catch (RuntimeException $error) { $rejected = true; }
ensure($rejected, 'Wrong extension accepted');
$page = (new BookStack\Entities\Models\Page())->forceFill(['pdf_attachment_id' => 1]);
$repo = app(BookStack\Entities\Repos\PageRepo::class);
foreach ([
    function () use ($repo, $page) { $repo->update($page, ['html' => 'changed']); },
    function () use ($repo, $page) { $repo->updatePageDraft($page, ['html' => 'changed']); },
    function () use ($repo, $page) { $repo->restoreRevision($page, 1); },
    function () use ($page) { (new BookStack\Entities\Tools\PageContent($page))->setNewHTML('changed'); },
    function () use ($page) { (new BookStack\Entities\Tools\PageContent($page))->setNewMarkdown('changed'); },
] as $operation) {
    $blocked = false;
    try { $operation(); } catch (Symfony\Component\HttpKernel\Exception\HttpException $error) { $blocked = $error->getStatusCode() === 403; }
    ensure($blocked, 'PDF content modification allowed');
}
echo "PDF: basic validation, scan/encrypted/51-page support, unchanged bytes and server-side read-only guards PASS\n";
