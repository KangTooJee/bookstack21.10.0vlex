@if($uploadedFiles->count())
<section class="mt-l" aria-label="File upload" data-book-files="{{ $book->id }}">
    <h2>File upload</h2>
    <div class="scroll-container"><table class="table vlex-book-files-table">
        <thead><tr><th>Tên file</th><th>Loại file</th><th>Dung lượng</th><th>Thao tác</th></tr></thead>
        <tbody>@foreach($uploadedFiles as $file)
        @php
            $extension = strtoupper(pathinfo($file->name, PATHINFO_EXTENSION));
            $type = in_array($extension, ['DOC', 'DOCX']) ? 'Word' : (in_array($extension, ['XLS', 'XLSX']) ? 'Excel' : ($extension === 'PDF' ? 'PDF' : 'File'));
        @endphp
        <tr data-uploaded-file="{{ $file->id }}">
            <td>
                <div class="vlex-book-file-name"><span class="text-pos" aria-hidden="true">✓</span><strong>{{ $file->name }}</strong></div>
                <span class="vlex-book-file-saved text-pos">Đã lưu</span>
            </td>
            <td><span class="vlex-upload-result__type">{{ $type }}{{ $extension && strtoupper($type) !== $extension ? ' · ' . $extension : '' }}</span></td>
            <td>{{ number_format($file->size / 1024, 1) }} KB</td>
            <td class="vlex-book-file-actions"><a href="{{ url('/files/' . $file->id . '/download') }}">Tải xuống</a>
                @if(signedInUser() && userCan('attachment-create', $file) && userCan('attachment-create-all'))
                <a class="block mt-s" href="{{ url('/files/' . $file->id . '/move') }}">Chuyển file</a>
                @endif
                @if(signedInUser() && userCan('attachment-delete', $file))
                <a class="block mt-s" href="{{ url('/files/' . $file->id . '/delete') }}">Xóa file</a>
                @endif
            </td>
        </tr>
        @endforeach</tbody>
    </table></div>
</section>
@endif
