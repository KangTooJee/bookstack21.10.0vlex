<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
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
