@extends('layouts.simple')
@push('head')
<link rel="stylesheet" href="{{ versioned_asset('dist/storage-upload.css') }}&amp;v={{ substr(hash_file('sha256', public_path('dist/storage-upload.css')), 0, 12) }}">
@endpush
@section('body')
<div class="container small">
    <div class="my-s">@include('entities.breadcrumbs', ['crumbs' => [$book]])</div>
    <main class="content-wrap card">
        <h1>Upload file vào {{ $book->name }}</h1>
        <p>File được lưu nguyên bản và xuất hiện cùng page trong book này.</p>
        @if($errors->any())<p class="text-neg" role="alert">{{ $errors->first() }}</p>@endif
        <form id="storage-upload" action="{{ $book->getUrl('/upload-files') }}" method="post" enctype="multipart/form-data">
            {{ csrf_field() }}
            <label for="storage-files">DOC, DOCX, XLS, XLSX, PDF — tối đa {{ config('vlex-storage.max_bytes') / 1048576 }} MB/file</label>
            <p>Kiểm tra từng file về định dạng, dung lượng, cấu trúc và khả năng đọc; cảnh báo tương thích với Office 2010 trở lên.</p>
            <input id="storage-files" type="file" name="files[]" multiple required accept=".doc,.docx,.xls,.xlsx,.pdf" data-max-bytes="{{ config('vlex-storage.max_bytes') }}">
            <button class="button">Kiểm tra và upload</button>
        </form>
        <div id="storage-results" role="list" aria-label="Kết quả upload từng file" aria-live="polite" class="vlex-upload-results my-m">
            @foreach(session('storage_results', []) as $result)
                @include('books.parts.upload-result', ['result' => $result])
            @endforeach
        </div>
        <a id="storage-refresh" href="{{ $book->getUrl() }}" class="button outline">Quay lại book và danh sách file</a>
    </main>
</div>
@stop
@section('scripts')
<script src="{{ versioned_asset('dist/storage-upload.js') }}&amp;v={{ substr(hash_file('sha256', public_path('dist/storage-upload.js')), 0, 12) }}" nonce="{{ $cspNonce }}"></script>
@stop
