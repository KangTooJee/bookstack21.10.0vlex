<?php

namespace BookStack\Entities\Repos;

class AttachedFileRepo
{
    public function moveDestinations(string $term, int $sourceBookId): \Illuminate\Support\Collection
    {
        $query = \BookStack\Entities\Models\Book::visible()->hasPermission('page-create')->where('id', '!=', $sourceBookId);
        if ($term !== '') {
            $query->whereIn('id', app(\BookStack\View\LibraryMap::class)->matchingBookIds($term));
        }
        return $query->orderBy('name')->limit(30)->get();
    }

    public function move(\BookStack\Entities\Models\AttachedFile $file, \BookStack\Entities\Models\Book $target): void
    {
        $file->book_id = $target->id;
        $file->save();
    }

    public function store(\BookStack\Entities\Models\Book $book, \Symfony\Component\HttpFoundation\File\UploadedFile $upload, array $warnings): \BookStack\Entities\Models\AttachedFile
    {
        $disk = \Illuminate\Support\Facades\Storage::disk('local_secure_attachments');
        $path = 'vlex-book-files/' . bin2hex(random_bytes(24));
        try {
            $stream = fopen($upload->getRealPath(), 'rb');
            try {
                if (!$disk->put($path, $stream)) throw new \RuntimeException('Cannot write uploaded file');
            } finally {
                fclose($stream);
            }
            return \BookStack\Entities\Models\AttachedFile::create([
                'book_id' => $book->id,
                'name' => mb_substr(str_replace(["\r", "\n", "\0", '/', '\\'], '_', $upload->getClientOriginalName()), 0, 255),
                'path' => $path, 'size' => $upload->getSize(),
                'sha256' => hash_file('sha256', $upload->getRealPath()),
                'warnings' => $warnings, 'created_by' => user()->id,
            ]);
        } catch (\Throwable $error) {
            $disk->delete($path);
            throw $error;
        }
    }

    public function destroy(\BookStack\Entities\Models\AttachedFile $file): void
    {
        abort_unless(\Illuminate\Support\Facades\Storage::disk('local_secure_attachments')->delete($file->path), 503, 'Không xóa được file.');
        $file->delete();
    }
}
