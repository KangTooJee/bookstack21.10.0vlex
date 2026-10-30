<?php

namespace BookStack\Http\Controllers;

use BookStack\Entities\Repos\PageRepo;
use BookStack\Uploads\AttachmentService;
use BookStack\Uploads\PdfImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class PdfImportController extends Controller
{
    public function create(PageRepo $pages, string $bookSlug, string $chapterSlug = null)
    {
        abort_unless($this->isSignedIn(), 403);
        $parent = $pages->getParentFromSlugs($bookSlug, $chapterSlug);
        $this->checkOwnablePermission('page-create', $parent);
        $this->checkPermission('attachment-create-all');
        $this->setPageTitle('Nhập PDF để xem');
        return view('pages.import-pdf', ['parent' => $parent]);
    }

    public function store(Request $request, PageRepo $pages, PdfImportService $importer, AttachmentService $files, string $bookSlug, string $chapterSlug = null)
    {
        abort_unless($this->isSignedIn(), 403);
        $parent = $pages->getParentFromSlugs($bookSlug, $chapterSlug);
        $this->checkOwnablePermission('page-create', $parent);
        $this->checkPermission('attachment-create-all');
        $this->validate($request, ['document' => 'required|file|max:20480', 'name' => 'nullable|string|max:255']);
        $upload = $request->file('document');
        try {
            $importer->validate($upload);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['document' => $exception->getMessage()]);
        }
        $name = trim($request->input('name', '')) ?: pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME);
        $attachment = null;
        try {
            $page = DB::transaction(function () use ($pages, $parent, $name, $upload, $files, &$attachment) {
                $draft = $pages->getNewDraftPage($parent);
                $attachment = $files->saveNewUpload($upload, $draft->id);
                if (hash('sha256', $files->getAttachmentFromStorage($attachment)) !== hash_file('sha256', $upload->getRealPath())) {
                    throw new RuntimeException('Không lưu được nguyên vẹn file PDF. Vui lòng nhập lại.');
                }
                $page = $pages->publishDraft($draft, [
                    'name' => mb_substr($name, 0, 255) ?: 'Tài liệu PDF',
                    'html' => '<p>Tài liệu PDF gốc. Chỉ xem và tải xuống.</p>',
                ]);
                $page->pdf_attachment_id = $attachment->id;
                $page->document_type = 'pdf';
                $page->save();
                return $page;
            });
        } catch (Throwable $exception) {
            if ($attachment !== null) {
                $files->deleteFile($attachment);
            }
            throw $exception;
        }
        $this->showSuccessNotification('Đã lưu PDF gốc. Bạn có thể xem trước và tải xuống nguyên vẹn.');
        return redirect($page->getUrl());
    }

    public function preview(PageRepo $pages, AttachmentService $files, string $bookSlug, string $pageSlug)
    {
        $page = $pages->getBySlug($bookSlug, $pageSlug);
        $this->checkOwnablePermission('page-view', $page);
        $pdf = $page->originalPdf();
        return response($files->getAttachmentFromStorage($pdf), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="document.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
