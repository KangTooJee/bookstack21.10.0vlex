<?php

namespace BookStack\Http\Controllers;

use BookStack\Entities\Repos\PageRepo;
use BookStack\Entities\Tools\PageContent;
use BookStack\Uploads\WordImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class WordImportController extends Controller
{
    public function create(PageRepo $pages, string $bookSlug, string $chapterSlug = null)
    {
        abort_unless($this->isSignedIn(), 403);
        $parent = $pages->getParentFromSlugs($bookSlug, $chapterSlug);
        $this->checkOwnablePermission('page-create', $parent);
        $this->setPageTitle('Nhập tài liệu Word');

        return view('pages.import-word', ['parent' => $parent]);
    }

    public function store(Request $request, PageRepo $pages, WordImportService $importer, string $bookSlug, string $chapterSlug = null)
    {
        abort_unless($this->isSignedIn(), 403);
        $parent = $pages->getParentFromSlugs($bookSlug, $chapterSlug);
        $this->checkOwnablePermission('page-create', $parent);
        $this->validate($request, [
            'document' => 'required|file|max:20480',
            'name' => 'nullable|string|max:255',
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
            $draft->document_type = 'word';
            $draft->name = mb_substr($name, 0, 255) ?: 'Tài liệu Word';
            (new PageContent($draft))->setNewHTML($html);
            $draft->save();

            return $draft;
        });

        $this->showSuccessNotification('Đã nhập Word. Kiểm tra nội dung rồi bấm Lưu trang để xuất bản.');

        return redirect($page->getUrl());
    }
}
