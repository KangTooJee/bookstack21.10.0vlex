<?php

require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use BookStack\Auth\User;
use BookStack\Entities\Models\Book;
use BookStack\Entities\Repos\PageRepo;
use BookStack\Entities\Tools\SearchOptions;
use BookStack\Entities\Tools\SearchRunner;
use BookStack\Entities\Tools\SearchScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

function ensure($value, $message) {
    if (!$value) { throw new RuntimeException($message); }
}

set_exception_handler(function (Throwable $error) { fwrite(STDERR, $error . "\n"); exit(1); });
auth()->login(User::query()->where('email', 'admin@admin.com')->firstOrFail());
$runner = app(SearchRunner::class);
$repo = app(PageRepo::class);
$token = 'Scope' . bin2hex(random_bytes(6));
$find = function ($term, $scope) use ($runner) {
    $options = SearchOptions::fromUserString($term);
    $options->documentScope = $scope;
    return $runner->searchEntities($options);
};

DB::beginTransaction();
try {
    $book = new Book();
    $book->forceFill(['name' => $token . 'Folder', 'slug' => strtolower($token), 'description' => $token . 'Description', 'created_by' => user()->id, 'owned_by' => user()->id]);
    $book->save();
    $book->rebuildPermissions();
    $pages = [];
    foreach (['word', 'excel', 'pdf'] as $type) {
        $draft = $repo->getNewDraftPage($book);
        $draft->document_type = $type;
        $draft->save();
        $pages[$type] = $repo->publishDraft($draft, ['name' => $token . 'Title ' . $type, 'html' => '<p>' . $token . 'Body</p>']);
    }
    foreach (['word', 'excel', 'pdf'] as $type) {
        $result = $find($token . 'Body', $type);
        $expectedBodyCount = $type === 'pdf' ? 0 : 1;
        ensure($result['total'] === $expectedBodyCount, 'Body scope failed: ' . $type);
        ensure($find('"' . $token . 'Body"', $type)['total'] === $expectedBodyCount, 'Exact scope failed: ' . $type);
        ensure($find('{inbody:' . $token . 'Body}', $type)['total'] === $expectedBodyCount, 'Advanced body scope failed: ' . $type);
        ensure($find($token . 'Title', $type)['total'] === 1, 'Title scope failed: ' . $type);
        ensure($find('"' . $token . 'Title ' . $type . '"', $type)['total'] === 1, 'Exact title failed: ' . $type);
        ensure($find($token . 'Title {type:book}', $type)['total'] === 0, 'Advanced type bypassed scope');
        ensure($runner->searchBook($book->id, $token . 'Body', $type)->count() === $expectedBodyCount, 'Book scope failed');
    }
    DB::enableQueryLog();
    ensure($find($token . 'Body', 'names')['total'] === 0, 'Names searched body');
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    foreach ($queries as $query) {
        ensure(strpos($query['query'], 'search_terms') === false, 'Names queried content index');
    }
    ensure($find('"' . $token . 'Body"', 'names')['total'] === 0, 'Exact searched body in names');
    ensure($find('{inbody:' . $token . 'Body}', 'names')['total'] === 0, 'inbody bypassed names');
    ensure($find($token . 'Title', 'names')['total'] === 3, 'Names missed a document type');
    ensure($find($token . 'Folder', 'names')['total'] === 1, 'Names missed folder');
    ensure($find($token . 'Description', 'names')['total'] === 0, 'Names searched folder description');
    $phrase = 'Mô tả chức năng (có vài cái)';
    $parsed = SearchOptions::fromUserString($phrase);
    ensure($parsed->rawQuery === $phrase && $parsed->exacts === [], 'Natural phrase was rewritten into exact fragments');
    ensure($parsed->searches === ['Mô','tả','chức','năng','có','vài','cái'], 'Parentheses tokenization failed');
    $excel = $repo->update($pages['excel'], ['html' => '<table><tr><td>' . $phrase . '</td><td>Nhân sự</td></tr></table>']);
    ensure(strpos($excel->text, ') Nhân') !== false, 'Adjacent cells were concatenated');
    ensure($find($phrase, 'excel')['results']->contains('id', $excel->id), 'Vietnamese phrase with parentheses was not found');
    ensure($find('"' . $phrase . '"', 'excel')['results']->contains('id', $excel->id), 'Quoted exact phrase was not found');
    ensure($find('Mô tả nonexistent' . $token, 'excel')['total'] === 0, 'Natural query used OR instead of requiring all words');
    // Restore common content for the permission checks below.
    $repo->update($pages['excel'], ['html' => '<p>' . $token . 'Body</p>']);
    DB::table('joint_permissions')->where('entity_type', $pages['excel']->getMorphClass())
        ->where('entity_id', $pages['excel']->id)->where('action', 'view')
        ->update(['has_permission' => false, 'has_permission_own' => false]);
    ensure($find($token . 'Body', 'excel')['total'] === 0, 'Content search bypassed view permission');
    ensure($find($token . 'Title', 'names')['total'] === 2, 'Names search bypassed view permission');
    foreach ([[], ['scope' => 'all'], ['scope' => ['word']], ['scope' => 'invalid']] as $input) {
        ensure(Validator::make($input, ['scope' => SearchScope::rules()])->fails(), 'Invalid/missing scope accepted');
    }
    echo "PASS: names only, title/body for Word/Excel/PDF, exact and advanced filters, book search, view permissions, required scope; fixtures rolled back.\n";
} finally {
    DB::rollBack();
}
