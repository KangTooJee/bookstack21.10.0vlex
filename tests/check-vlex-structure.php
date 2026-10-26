<?php

require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

function verifyStructure($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo 'PASS: '.$message."\n";
}

$map = json_decode(file_get_contents('/tmp/vlex-file-map.json'), true, 512, JSON_THROW_ON_ERROR);
$aliases = 0;
$stubs = 0;
$linted = 0;
foreach ($map as $entry) {
    $relative = $entry['path'];
    $path = '/var/www/html/'.$relative;
    if (in_array($entry['status'], ['compatibility-alias', 'scaffold', 'inactive-migration', 'compatibility-loader']) && preg_match('/\.php(?:\.stub)?$/', $relative)) {
        exec('php -l '.escapeshellarg($path).' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Invalid PHP: '.$relative.' '.implode("\n", $output));
        }
        $linted++;
    }
    if ($entry['status'] === 'compatibility-alias') {
        $class = 'BookStack\\'.str_replace('/', '\\', substr($relative, 4, -4));
        verifyStructure(class_exists($class) || interface_exists($class) || trait_exists($class), 'Compatibility path loads: '.$relative);
        $target = 'BookStack\\'.str_replace('/', '\\', substr($entry['note'], 4, -4));
        verifyStructure((new ReflectionClass($class))->getName() === (new ReflectionClass($target))->getName(), 'Alias preserves original identity: '.$relative);
        $aliases++;
    }
    if ($entry['status'] === 'inactive-migration') {
        verifyStructure(is_file($path) && !is_file(substr($path, 0, -5)), 'Unknown schema remains inactive: '.$relative);
        $stubs++;
    }
}
$routes = app('router')->getRoutes();
$prototypeActions = [];
foreach ($routes as $route) {
    $action = $route->getActionName();
    if (strpos($action, 'VlexWorkspaceController') !== false) {
        $prototypeActions[] = $route->uri();
    }
}
verifyStructure(!$prototypeActions, 'Standalone workspace actions removed');
verifyStructure(is_a(new BookStack\Permissions\User(), BookStack\Auth\User::class), 'Compatibility User remains native BookStack User');
verifyStructure(config('auth.providers.users.model') === BookStack\Auth\User::class, 'Original auth model retained');
verifyStructure(class_exists(BookStack\Http\Controllers\BookController::class) && class_exists(BookStack\Http\Controllers\PageController::class), 'Native book/page controllers load');
try {
    new BookStack\Entities\Models\AttachedFile();
    throw new RuntimeException('Unimplemented model silently initialized');
} catch (LogicException $error) {
    verifyStructure(strpos($error->getMessage(), 'VLEX scaffold') !== false, 'Unimplemented feature fails explicitly');
}
$migrationNames = array_map('basename', app('migrator')->getMigrationFiles(['/var/www/html/database/migrations']));
verifyStructure(count(array_filter($migrationNames, function ($name) { return strpos($name, '.stub') !== false; })) === 0, 'Artisan excludes migration stubs');
echo "Checked {$aliases} aliases, {$stubs} inactive migrations and {$linted} PHP files.\n";
