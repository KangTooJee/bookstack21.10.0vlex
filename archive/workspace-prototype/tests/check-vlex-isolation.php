<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use BookStack\Http\Controllers\VlexWorkspaceController;

Auth::login(BookStack\Auth\User::findOrFail(1));
DB::beginTransaction();
try {
    $foreign = DB::table('vlex_nodes')->insertGetId([
        'user_id' => 4294967294, 'kind' => 'file', 'name' => 'Isolation fixture',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $controller = new VlexWorkspaceController();
    foreach (['view', 'download', 'update'] as $operation) {
        try {
            if ($operation === 'view') {
                $controller->index(Request::create('/vlex', 'GET', ['node' => $foreign]));
            } elseif ($operation === 'download') {
                $controller->download(Request::create('/vlex/files/'.$foreign), $foreign);
            } else {
                $controller->update(Request::create('/vlex/nodes/'.$foreign, 'POST', ['action' => 'trash']), $foreign);
            }
            throw new RuntimeException('Cross-owner '.$operation.' was allowed');
        } catch (Symfony\Component\HttpKernel\Exception\HttpException $error) {
            if ($error->getStatusCode() !== 404) {
                throw $error;
            }
            echo 'PASS: Cross-owner '.$operation." rejected with 404\n";
        }
    }
} finally {
    DB::rollBack();
}
