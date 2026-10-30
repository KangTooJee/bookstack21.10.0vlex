<?php

require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use BookStack\Uploads\StorageFileInspector;
use BookStack\Entities\Models\AttachedFile;
use BookStack\Entities\Repos\AttachedFileRepo;
use BookStack\Entities\Repos\BookRepo;
use BookStack\Entities\Repos\BookshelfRepo;
use BookStack\Http\Controllers\FileAttachedController;
use BookStack\View\LibraryMap;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\UploadedFile;

set_exception_handler(function (Throwable $error) { fwrite(STDERR, $error . "\n"); exit(1); });
function ensure($condition, $message) { if (!$condition) throw new RuntimeException($message); }
$directory = sys_get_temp_dir() . '/storage-test-' . bin2hex(random_bytes(6));
mkdir($directory, 0700);
$inspector = app(StorageFileInspector::class);
$upload = function ($path, $name = null, $error = null) { return new UploadedFile($path, $name ?? basename($path), null, $error, true); };
try {
    $pdf = app('dompdf.wrapper')->loadHTML('<h1>Storage validation</h1><p>Original bytes</p>');
    file_put_contents($directory . '/valid.pdf', $pdf->output());
    $encrypted = app('dompdf.wrapper')->loadHTML('<p>Secret</p>');
    $encrypted->getDomPDF()->render();
    $encrypted->getDomPDF()->getCanvas()->get_cpdf()->setEncryption('secret', 'owner', []);
    file_put_contents($directory . '/encrypted.pdf', $encrypted->getDomPDF()->output());
    file_put_contents($directory . '/fake.pdf', '%PDF-1.4\nnot a pdf\n%%EOF');
    file_put_contents($directory . '/empty.doc', '');
    $fixtures = [
        '/tmp/storage-fixtures/fixture.doc', '/tmp/storage-fixtures/fixture.docx',
        '/tmp/storage-fixtures/multi.xls', '/tmp/storage-fixtures/fixture.xlsx', $directory . '/valid.pdf',
    ];
    foreach ($fixtures as $path) {
        $hash = hash_file('sha256', $path);
        $result = $inspector->inspect($upload($path));
        ensure(!$result['errors'], basename($path) . ': ' . implode('; ', $result['errors']));
        ensure(count($result['warnings']) > 0, 'Compatibility limitations missing');
        ensure($hash === hash_file('sha256', $path), 'Original was modified');
        echo 'PASS readable original ' . basename($path) . "\n";
    }
    $invalid = [
        $upload($directory . '/fake.pdf'), $upload($directory . '/encrypted.pdf'), $upload($directory . '/empty.doc'),
        $upload($directory . '/valid.pdf', 'wrong.exe'), $upload($directory . '/valid.pdf', 'wrong.docx'),
        $upload('/tmp/storage-fixtures/fixture.docx', 'wrong.xlsx'),
        $upload('/tmp/storage-fixtures/multi.xls', 'wrong.doc'),
        $upload($directory . '/valid.pdf', 'partial.pdf', UPLOAD_ERR_PARTIAL),
    ];
    foreach ($invalid as $file) {
        ensure(count($inspector->inspect($file)['errors']) > 0, 'Invalid accepted: ' . $file->getClientOriginalName());
    }
    $limit = config('vlex-storage.max_bytes');
    config(['vlex-storage.max_bytes' => filesize($directory . '/valid.pdf')]);
    ensure(!$inspector->inspect($upload($directory . '/valid.pdf'))['errors'], 'Exact size limit rejected');
    config(['vlex-storage.max_bytes' => filesize($directory . '/valid.pdf') - 1]);
    ensure(count($inspector->inspect($upload($directory . '/valid.pdf'))['errors']) > 0, 'Oversize accepted');
    config(['vlex-storage.max_bytes' => $limit]);
    copy('/tmp/storage-fixtures/fixture.docx', $directory . '/broken.docx');
    $zip = new ZipArchive(); $zip->open($directory . '/broken.docx');
    $zip->addFromString('word/document.xml', '<broken>'); $zip->close();
    ensure(count($inspector->inspect($upload($directory . '/broken.docx'))['errors']) > 0, 'Broken XML accepted');
    copy('/tmp/storage-fixtures/fixture.docx', $directory . '/old-mode.docx');
    $zip->open($directory . '/old-mode.docx');
    $zip->addFromString('word/settings.xml', '<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:compat><w:compatSetting w:val="12" w:name="compatibilityMode" w:uri="http://schemas.microsoft.com/office/word"/></w:compat></w:settings>');
    $zip->close();
    $old = $inspector->inspect($upload($directory . '/old-mode.docx'));
    ensure(!$old['errors'] && count($old['warnings']) === 2, 'Pre-2010 compatibility mode not warned');
    copy('/tmp/storage-fixtures/fixture.docx', $directory . '/crc.docx');
    $zip->open($directory . '/crc.docx');
    $main = $zip->getFromName('word/document.xml');
    $zip->setCompressionName('word/document.xml', ZipArchive::CM_STORE); $zip->close();
    $bytes = file_get_contents($directory . '/crc.docx');
    $offset = strpos($bytes, $main);
    ensure($offset !== false, 'CRC fixture not stored');
    $bytes[$offset + 10] = $bytes[$offset + 10] === 'a' ? 'b' : 'a';
    file_put_contents($directory . '/crc.docx', $bytes);
    ensure(count($inspector->inspect($upload($directory . '/crc.docx'))['errors']) > 0, 'Corrupt CRC accepted');
    echo "PASS wrong extensions, fake PDF, encrypted PDF, empty, partial upload, size boundary, malformed XML, CRC, pre-2010 mode\n";

    auth()->login(BookStack\Auth\User::where('email', 'admin@admin.com')->firstOrFail());
    DB::beginTransaction();
    $path = null;
    $purgePath = null;
    try {
        $books = app(BookRepo::class); $shelves = app(BookshelfRepo::class); $map = app(LibraryMap::class);
        $shelfA = $shelves->create(['name' => 'Test shelf A', 'description' => ''], []);
        $shelfB = $shelves->create(['name' => 'Test shelf B', 'description' => ''], []);
        $root = $books->create(['name' => 'Test root', 'description' => '']);
        $child = $books->create(['name' => 'Test child', 'description' => '']);
        $leaf = $books->create(['name' => 'Test leaf', 'description' => '']);
        $map->place($root, null, $shelfA, true);
        $map->place($child, $root, null, true);
        $map->place($leaf, $child, null, true);
        $otherShelfBook = $books->create(['name' => 'Destination book B', 'description' => '']);
        $map->place($otherShelfBook, null, $shelfB, true);
        $targetIds = app(AttachedFileRepo::class)->moveDestinations('Test shelf B', $leaf->id)->pluck('id')->all();
        ensure(in_array($otherShelfBook->id, $targetIds) && !in_array($child->id, $targetIds), 'Destination search did not scope matches to the selected shelf');
        $targetIds = app(AttachedFileRepo::class)->moveDestinations('Test root', $leaf->id)->pluck('id')->all();
        ensure(in_array($child->id, $targetIds) && !in_array($leaf->id, $targetIds), 'Ancestor search/current destination exclusion failed');
        ensure($map->path($leaf) === 'Test shelf A / Test root / Test child / Test leaf', 'Nested path is wrong');
        ensure(!$child->shelves()->exists() && !$leaf->shelves()->exists(), 'Nested books retain direct shelf links');
        $pageCount = BookStack\Entities\Models\Page::count();
        $file = app(AttachedFileRepo::class)->store($leaf, $upload($directory . '/valid.pdf'), ['Test warning']);
        $path = $file->path;
        ensure(BookStack\Entities\Models\Page::count() === $pageCount, 'Raw file was converted into a Page');
        $request = Illuminate\Http\Request::create('/files/' . $file->id . '/move', 'POST', ['book_id' => $child->id], [], [], ['HTTP_ACCEPT' => 'application/json']);
        app()->instance('request', $request);
        $controller = app(FileAttachedController::class);
        $controller->move($request, $file->id);
        ensure(AttachedFile::where('book_id', $leaf->id)->count() === 0, 'Old book retains file');
        ensure(AttachedFile::where('book_id', $child->id)->count() === 1, 'Target book missing/duplicated file');
        ensure(AttachedFile::find($file->id)->path === $path, 'Move copied blob');
        ensure(hash('sha256', Storage::disk('local_secure_attachments')->get($path)) === hash_file('sha256', $directory . '/valid.pdf'), 'Move changed bytes');
        $map->place($child, null, $shelfB);
        ensure(!$root->childBooks()->whereKey($child->id)->exists(), 'Old parent retains child book');
        ensure($shelfB->contains($child) && $child->fresh()->parent_book_id === null, 'Move to shelf failed');
        ensure($leaf->fresh()->parent_book_id === $child->id && AttachedFile::find($file->id)->book_id === $child->id, 'Moving book did not retain subtree/files');
        try { $map->place($child, $leaf, null); throw new RuntimeException('Cycle accepted'); }
        catch (Illuminate\Validation\ValidationException $error) {}
        ensure($shelfB->contains($child), 'Cycle rejection changed placement');
        $map->place($child, $root, null);
        ensure(!$shelfB->contains($child) && !$child->shelves()->exists(), 'Move into book retains old shelf');
        $shelves->update($shelfB, [], [$root->id]);
        ensure(!$shelfA->contains($root) && $shelfB->contains($root), 'Shelf editing duplicated instead of moving root book');
        ensure($root->shelves()->count() === 1, 'Root book belongs to multiple shelves');
        $trash = new BookStack\Entities\Tools\TrashCan();
        $independent = $books->create(['name' => 'Previously deleted child', 'description' => '']);
        $map->place($independent, $root, null, true);
        $trash->softDestroyBook($independent);
        $trash->softDestroyBook($root);
        ensure(!BookStack\Entities\Models\Book::find($leaf->id), 'Nested book remained active when parent was trashed');
        ensure(AttachedFile::find($file->id) && Storage::disk('local_secure_attachments')->exists($path), 'Trashing Book destroyed original file');
        $trash->restoreFromDeletion($root->deletions()->firstOrFail());
        ensure(BookStack\Entities\Models\Book::find($leaf->id), 'Nested Book not restored with parent');
        ensure(!BookStack\Entities\Models\Book::find($independent->id), 'Independently deleted Book was unexpectedly restored');
        $admin = user();
        $limited = new BookStack\Auth\User();
        $limited->forceFill(['name' => 'Storage test no permissions', 'email' => 'storage-' . bin2hex(random_bytes(8)) . '@example.test', 'password' => bcrypt('unused')]);
        $limited->save();
        auth()->login($limited);
        app()->forgetInstance('permissions');
        BookStack\Facades\Permissions::clearResolvedInstance('permissions');
        foreach (['download', 'showMove', 'showDelete'] as $method) {
            try { $controller->$method($file->id); throw new RuntimeException('User without book view accessed file action ' . $method); }
            catch (Illuminate\Database\Eloquent\ModelNotFoundException $error) {}
        }
        auth()->login($admin);
        app()->forgetInstance('permissions');
        BookStack\Facades\Permissions::clearResolvedInstance('permissions');
        $controller->destroy($file->id, app(AttachedFileRepo::class));
        ensure(!AttachedFile::find($file->id) && !Storage::disk('local_secure_attachments')->exists($path), 'Delete left a trace');
        $purgeFile = app(AttachedFileRepo::class)->store($leaf->fresh(), $upload($directory . '/valid.pdf'), []);
        $purgePath = $purgeFile->path;
        $trash->softDestroyBook($root->fresh());
        $trash->destroyFromDeletion($root->deletions()->firstOrFail());
        ensure(!BookStack\Entities\Models\Book::withTrashed()->find($leaf->id), 'Purge retained nested Book');
        ensure(!AttachedFile::find($purgeFile->id) && !Storage::disk('local_secure_attachments')->exists($purgePath), 'Book purge retained file/blob');
        echo "PASS real shelf > book > book > book + raw file, single parent, atomic move, subtree retention, cycle rejection, native book permissions, unchanged bytes, permanent delete\n";
        echo "PASS nested trash/restore, independently deleted Book retained in trash, recursive purge removes original blobs\n";
    } finally {
        DB::rollBack();
        if ($path) Storage::disk('local_secure_attachments')->delete($path);
        if ($purgePath) Storage::disk('local_secure_attachments')->delete($purgePath);
    }
} finally {
    Illuminate\Support\Facades\File::deleteDirectory($directory);
}
