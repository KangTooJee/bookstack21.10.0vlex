<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error) { fwrite(STDERR, $error->getMessage() . "\n"); exit(1); });
function ensure($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}
ensure(PHP_VERSION === '8.0.30', 'PHP version mismatch');
ensure(Illuminate\Foundation\Application::VERSION === '8.70.2', 'Laravel version mismatch');
ensure(trim(file_get_contents('/var/www/html/version')) === 'v21.11.1', 'BookStack version mismatch');
ensure(strtoupper(hash_file('sha256', '/var/www/html/composer.lock')) === '081369FDFE308E94E3A852E5FC0FF1A37C682A01DCAB9B269B4AACB87F6772E0', 'Original composer.lock changed');
$lock = json_decode(file_get_contents('/var/www/html/composer.lock'), true);
foreach ($lock['packages'] as $package) {
    ensure(Composer\InstalledVersions::isInstalled($package['name']), 'Missing package: ' . $package['name']);
    ensure(Composer\InstalledVersions::getPrettyVersion($package['name']) === $package['version'], 'Package version mismatch: ' . $package['name']);
}
ensure(!is_file('/usr/local/bin/composer') && !is_file('/usr/bin/composer'), 'Composer executable present in runtime');
$version = Illuminate\Support\Facades\DB::selectOne('SELECT VERSION() AS version')->version;
ensure(strpos($version, '8.0.36') === 0, 'MySQL version mismatch');
if (in_array('--empty', $argv, true)) {
    foreach (['books', 'chapters', 'pages', 'bookshelves', 'images'] as $table) {
        ensure(Illuminate\Support\Facades\DB::table($table)->count() === 0, 'Database not empty: ' . $table);
    }
    echo "Fresh database: no books, chapters, pages, shelves or images PASS\n";
}
echo 'BookStack v21.11.1 / PHP ' . PHP_VERSION . ' / Laravel ' . Illuminate\Foundation\Application::VERSION . ' / MySQL ' . $version . " PASS\n";
echo count($lock['packages']) . " production packages match original lockfile; Composer absent at runtime PASS\n";
