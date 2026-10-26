<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error) { fwrite(STDERR, $error->getMessage() . "\n"); exit(1); });
$service = $app->make(BookStack\Uploads\WordImportService::class);
$fixtureDirectory = '/tmp/word-import-test';
$image = imagecreatetruecolor(20, 20);
imagefill($image, 0, 0, imagecolorallocate($image, 30, 100, 200));
imagepng($image, $fixtureDirectory . '/fixture.png');
imagedestroy($image);
$fixtureHtml = str_replace('src="fixture.png"', 'src="data:image/png;base64,' . base64_encode(file_get_contents($fixtureDirectory . '/fixture.png')) . '"', file_get_contents($fixtureDirectory . '/fixture.html'));
file_put_contents($fixtureDirectory . '/fixture-source.html', $fixtureHtml);
foreach (['doc:MS Word 97', 'docx:Office Open XML Text'] as $format) {
    $process = new Symfony\Component\Process\Process([
        'libreoffice', '-env:UserInstallation=file://' . $fixtureDirectory . '/profile-fixture',
        '--headless', '--convert-to', $format, '--outdir', $fixtureDirectory, $fixtureDirectory . '/fixture-source.html',
    ]);
    $process->setTimeout(60);
    $process->mustRun();
    $extension = explode(':', $format)[0];
    rename($fixtureDirectory . '/fixture-source.' . $extension, $fixtureDirectory . '/fixture.' . $extension);
}
$sanitize = new ReflectionMethod($service, 'cleanHtml');
$sanitize->setAccessible(true);
$html = $sanitize->invoke($service, '<body><script>alert(1)</script><p onclick="bad()" style="color:red;background-image:url(http://bad)">safe</p><a href="javascript:bad()">bad</a><table><tr><td>cell</td></tr></table></body>', '/tmp');
foreach (['<script', 'onclick', 'javascript:', 'background-image', 'url('] as $bad) {
    if (strpos($html, $bad) !== false) { throw new RuntimeException('Unsafe HTML: ' . $bad); }
}
if (strpos($html, '<table') === false || strpos($html, 'color: red') === false) { throw new RuntimeException('Content sanitization lost formatting'); }
$drawingHtml = '<body><p>Before drawings</p>' .
    '<img src="missing-shape.gif" name="Shape2" alt="Shape2" align="left" hspace="1" vspace="1">' .
    '<span id="Frame1" style="float: left; width: 0.66in; height: 0.31in"><p>Stray annotation</p><img src="missing-frame.png"></span>' .
    '<span id="Frame2" style="FLOAT:RIGHT"><p>Another annotation</p></span>' .
    '<p id="normal-bookmark">Visible body</p><span id="Frame3">Normal inline text</span>' .
    '<span id="custom-frame" style="float: left">Custom content</span>' .
    '<img src="fixture.png" name="Shape1" alt="Shape1" align="left" width="20" height="20">' .
    '<img src="fixture.png" name="Picture 1" align="left">' .
    '<img src="fixture.png" alt="Shape5"></body>';
$drawingHtml = $sanitize->invoke($service, $drawingHtml, $fixtureDirectory);
foreach (['Stray annotation', 'Another annotation', 'missing-shape', 'missing-frame', 'name="Frame1"', 'name="Frame2"'] as $unwanted) {
    if (strpos($drawingHtml, $unwanted) !== false) { throw new RuntimeException('Floating drawing artifact survived: ' . $unwanted); }
}
foreach (['Before drawings', 'Visible body', 'Normal inline text', 'Custom content', 'name="normal-bookmark"', 'name="Frame3"', 'alt="Shape1"', 'alt="Shape5"'] as $expected) {
    if (strpos($drawingHtml, $expected) === false) { throw new RuntimeException('Drawing cleanup lost content: ' . $expected); }
}
if (substr_count($drawingHtml, 'data:image/png;base64,') !== 3) { throw new RuntimeException('Drawing cleanup lost real images'); }
echo "Floating drawing artifacts removed; body, bookmarks and real images preserved PASS\n";
foreach (['bad.doc', 'bad.docx', 'bad.txt'] as $name) {
    $path = '/tmp/word-import-test/' . $name;
    file_put_contents($path, 'invalid document');
    $failed = false;
    try { $service->convert(new Symfony\Component\HttpFoundation\File\UploadedFile($path, $name, null, null, true)); }
    catch (RuntimeException $e) { $failed = true; }
    if (!$failed) { throw new RuntimeException('Invalid document accepted: ' . $name); }
}
foreach (['doc', 'docx'] as $extension) {
    $path = '/tmp/word-import-test/fixture.' . $extension;
    $converted = $service->convert(new Symfony\Component\HttpFoundation\File\UploadedFile($path, 'fixture.' . $extension, null, null, true));
    $converted = preg_replace('/\s+/u', ' ', $converted);
    foreach (['Tiêu đề tiếng Việt', 'Mục lục', 'href="#word-heading"', 'name="word-heading"', 'Dữ liệu 123', 'Ô gộp', 'KẾT THÚC TÀI LIỆU', '<table', '<ol', 'data:image/'] as $expected) {
        if (strpos($converted, $expected) === false) { throw new RuntimeException($extension . ' lost content: ' . $expected); }
    }
    file_put_contents('/tmp/word-import-test/converted-' . $extension . '.html', $converted);
    echo strtoupper($extension) . ": text, Vietnamese, table, merged cell, list and embedded image PASS\n";
}
echo "Invalid files and HTML sanitization PASS\n";
require __DIR__ . '/check-docx-layout.php';
