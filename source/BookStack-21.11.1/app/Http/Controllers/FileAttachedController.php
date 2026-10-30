<?php

namespace BookStack\Http\Controllers;

use BookStack\Entities\Models\AttachedFile;
use BookStack\Entities\Models\Book;
use BookStack\Entities\Repos\AttachedFileRepo;
use BookStack\Entities\Repos\BookRepo;
use BookStack\Uploads\StorageFileInspector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FileAttachedController extends Controller
{
    private function visibleFile(int $id): AttachedFile
    {
        $file = AttachedFile::findOrFail($id);
        $file->setRelation('book', Book::visible()->findOrFail($file->book_id));
        return $file;
    }

    public function showMove(int $id)
    {
        $file = $this->visibleFile($id);
        $this->checkOwnablePermission('attachment-create', $file);
        $this->checkPermission('attachment-create-all');
        $this->setPageTitle('Chuyển file ' . $file->name);
        return view('files.move', ['file' => $file, 'book' => $file->book]);
    }

    public function moveDestinations(Request $request, int $id, AttachedFileRepo $files)
    {
        $file = $this->visibleFile($id);
        $this->checkOwnablePermission('attachment-create', $file);
        $this->checkPermission('attachment-create-all');
        $request->validate(['term' => 'nullable|string|max:200']);
        $targets = $files->moveDestinations(trim($request->query('term', '')), $file->book_id);
        return view('files.parts.move-destinations', compact('targets'));
    }

    public function showDelete(int $id)
    {
        $file = $this->visibleFile($id);
        $this->checkOwnablePermission('attachment-delete', $file);
        $this->setPageTitle('Xóa file ' . $file->name);
        return view('files.delete', ['file' => $file, 'book' => $file->book]);
    }

    private function writableBook(Book $book): void
    {
        $this->checkPermission('attachment-create-all');
        $this->checkOwnablePermission('page-create', $book);
    }

    public function create(string $bookSlug, BookRepo $books)
    {
        $book = $books->getBySlug($bookSlug);
        $this->writableBook($book);
        $this->setPageTitle('Upload file vào ' . $book->name);
        return view('books.upload-files', compact('book'));
    }

    public function store(Request $request, string $bookSlug, BookRepo $books, AttachedFileRepo $files, StorageFileInspector $inspector)
    {
        $book = $books->getBySlug($bookSlug);
        $this->writableBook($book);
        $request->validate(['files' => 'required|array|max:20']);
        $results = [];
        foreach ($request->file('files', []) as $upload) {
            if (!$upload instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                $results[] = ['name' => 'File không hợp lệ', 'status' => 'rejected', 'errors' => ['Dữ liệu upload không hợp lệ.'], 'warnings' => []];
                continue;
            }
            $row = ['name' => $upload->getClientOriginalName(), 'status' => 'rejected', 'errors' => [], 'warnings' => []];
            try {
                $inspection = $inspector->inspect($upload);
                $row['errors'] = $inspection['errors'];
                $row['warnings'] = $inspection['warnings'];
                if (!$inspection['errors']) {
                    $file = $files->store($book, $upload, $inspection['warnings']);
                    $row['status'] = 'stored';
                    $row['id'] = $file->id;
                }
            } catch (\Throwable $error) {
                report($error);
                $row['errors'][] = 'Không hoàn tất kiểm tra hoặc lưu file trên máy chủ. Hãy thử lại.';
            }
            $results[] = $row;
        }
        if (!$results) return response()->json(['message' => 'Không nhận được file upload.'], 422);
        if ($request->wantsJson()) return response()->json(['results' => $results]);
        return redirect($book->getUrl('/upload-files'))->with('storage_results', $results);
    }

    public function download(int $id)
    {
        $file = AttachedFile::findOrFail($id);
        Book::visible()->findOrFail($file->book_id);
        return Storage::disk('local_secure_attachments')->download($file->path, $file->name, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function move(Request $request, int $id)
    {
        if ($request->has('entity_selection')) {
            $request->validate(['entity_selection' => ['required', 'string', 'regex:/^book:[1-9][0-9]*$/']], [
                'entity_selection.required' => 'Hãy tìm và chọn book đích trước khi chuyển file.',
                'entity_selection.regex' => 'Vị trí đích phải là một book.',
            ]);
            $targetId = (int) substr($request->input('entity_selection'), 5);
        } else {
            $request->validate(['book_id' => 'required|integer|min:1']);
            $targetId = (int) $request->input('book_id');
        }
        $target = DB::transaction(function () use ($targetId, $id) {
            $file = AttachedFile::whereKey($id)->lockForUpdate()->firstOrFail();
            Book::visible()->findOrFail($file->book_id);
            $this->checkOwnablePermission('attachment-create', $file);
            $target = Book::visible()->findOrFail($targetId);
            $this->writableBook($target);
            if ((int) $file->book_id === (int) $target->id) {
                throw \Illuminate\Validation\ValidationException::withMessages(['entity_selection' => 'Hãy chọn book khác vị trí hiện tại.']);
            }
            app(AttachedFileRepo::class)->move($file, $target);
            return $target;
        });
        return redirect($target->getUrl());
    }

    public function destroy(int $id, AttachedFileRepo $files)
    {
        $book = DB::transaction(function () use ($id, $files) {
            $file = AttachedFile::whereKey($id)->lockForUpdate()->firstOrFail();
            $book = Book::visible()->findOrFail($file->book_id);
            $this->checkOwnablePermission('attachment-delete', $file);
            $files->destroy($file);
            return $book;
        });
        return redirect($book->getUrl());
    }
}
