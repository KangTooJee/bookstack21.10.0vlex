<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error) { fwrite(STDERR, $error . "\n"); exit(1); });
use BookStack\Auth\Permissions\PermissionService;
use BookStack\Auth\Permissions\RolePermission;
use BookStack\Auth\Role;
use BookStack\Auth\User;
use BookStack\Entities\Models\Book;
use BookStack\Entities\Models\Page;
use BookStack\Entities\Repos\PageRepo;
use BookStack\Http\Controllers\ExcelImportController;
use BookStack\Uploads\ExcelImportService;
use Illuminate\Support\Facades\DB;

function denied(callable $action, string $message): void {
    try { $action(); }
    catch (Illuminate\Http\Exceptions\HttpResponseException $error) {
        if ($error->getResponse()->getStatusCode() === 403) { return; }
        throw $error;
    }
    catch (Illuminate\Auth\Access\AuthorizationException $error) { return; }
    throw new RuntimeException($message);
}
DB::beginTransaction();
try {
    $role = Role::create(['display_name'=>'Excel permission test']);
    $role->permissions()->sync(RolePermission::whereIn('name', ['book-view-all', 'page-view-all'])->pluck('id'));
    $user = new User(); $user->name = 'Excel permission test'; $user->email = bin2hex(random_bytes(8)) . '@example.invalid'; $user->password = bcrypt(bin2hex(random_bytes(16))); $user->save();
    $user->roles()->attach($role);
    auth()->setUser($user);
    $book = new Book(); $book->name = 'Excel permission test'; $book->slug = 'excel-permission-test-' . bin2hex(random_bytes(5)); $book->save();
    $page = new Page(); $page->book_id = $book->id; $page->name = 'Private test'; $page->slug = 'private-test'; $page->html = '<p>private</p>'; $page->draft = false; $page->save();
    $permissions = $app->make(PermissionService::class);
    $permissions->buildJointPermissionsForEntity($book);
    $pages = $app->make(PageRepo::class);
    if ($pages->getBySlug($book->slug, $page->slug)->id !== $page->id) { throw new RuntimeException('Test viewer cannot see page'); }
    $request = Illuminate\Http\Request::create('/permission-test', 'POST', [], [], [], ['HTTP_ACCEPT'=>'application/json']);
    $app->instance('request', $request);
    $controller = $app->make(ExcelImportController::class);
    denied(fn() => $controller->create($pages, $book->slug), 'Import form allowed without page-create');
    denied(fn() => $controller->store($request, $pages, $app->make(ExcelImportService::class), $book->slug), 'Import allowed without page-create');
    $middleware = new Illuminate\Auth\Middleware\Authorize($app->make(Illuminate\Contracts\Auth\Access\Gate::class));
    denied(fn() => $middleware->handle($request, fn() => response('unexpected'), 'content-export'), 'Export allowed without content-export');
    echo "Import form/upload require page-create; export middleware requires content-export PASS\n";
    $role->permissions()->attach(RolePermission::where('name', 'page-create-all')->firstOrFail()->id);
    $user->load('roles');
    $file = new Illuminate\Http\UploadedFile(__DIR__ . '/fixture.xlsx', 'fixture.xlsx', null, null, true);
    $request->files->set('document', $file);
    denied(fn() => $controller->store($request, $pages, $app->make(ExcelImportService::class), $book->slug), 'Image import allowed without image-create');
    echo "Import with images requires image-create before draft creation PASS\n";
    $role->permissions()->detach(RolePermission::where('name', 'page-view-all')->firstOrFail()->id);
    $user->load('roles');
    $permissions->buildJointPermissionsForEntity($book);
    $hidden = false;
    try { $pages->getBySlug($book->slug, $page->slug); } catch (BookStack\Exceptions\NotFoundException $error) { $hidden = true; }
    if (!$hidden) { throw new RuntimeException('Page without view permission is accessible to export'); }
    echo "Export PageRepo scope hides pages without view access PASS\n";
} finally {
    DB::rollBack();
}
