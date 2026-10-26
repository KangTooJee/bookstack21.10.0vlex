<?php

namespace BookStack\Http\Controllers;

use BookStack\Entities\Repos\PageRepo;
use BookStack\Entities\Tools\PageContent;
use BookStack\Uploads\ExcelImportService;
use BookStack\Uploads\ExcelImportPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ExcelImportController extends Controller
{
    public function create(PageRepo $pages, string $bookSlug, string $chapterSlug = null)
    {
        abort_unless($this->isSignedIn(), 403);
        $parent = $pages->getParentFromSlugs($bookSlug, $chapterSlug);
        $this->checkOwnablePermission('page-create', $parent);
        $this->setPageTitle('Nhập tài liệu Excel');

        return view('pages.import-excel', ['parent' => $parent]);
    }

    public function store(Request $request, PageRepo $pages, ExcelImportService $importer, string $bookSlug, string $chapterSlug = null)
    {
        abort_unless($this->isSignedIn(), 403);
        $parent = $pages->getParentFromSlugs($bookSlug, $chapterSlug);
        $this->checkOwnablePermission('page-create', $parent);
        $this->validate($request, [
            'document' => 'required|file|max:' . ExcelImportPolicy::MAX_KB,
            'name' => 'nullable|string|max:255',
        ], [
            'document.max' => 'File Excel vượt quá ' . ExcelImportPolicy::MAX_KB . ' KB. Hãy tách file trước khi nhập.',
        ]);

        try {
            $html = $importer->convert($request->file('document'));
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['document' => $exception->getMessage()]);
        }
        if (strpos($html, 'data:image/') !== false) {
            $this->checkPermission('image-create-all');
        }

        $name = trim($request->input('name', ''));
        if ($name === '') {
            $name = pathinfo($request->file('document')->getClientOriginalName(), PATHINFO_FILENAME);
        }

        $page = DB::transaction(function () use ($pages, $parent, $name, $html) {
            $draft = $pages->getNewDraftPage($parent);
            $draft->name = mb_substr($name, 0, 255) ?: 'Tài liệu Excel';
            (new PageContent($draft))->setNewHTML($html);
            $draft->save();

            return $draft;
        });

        $this->showSuccessNotification('Đã nhập Excel. Kiểm tra nội dung rồi bấm Lưu trang để xuất bản.');

        return redirect($page->getUrl());
    }
}
