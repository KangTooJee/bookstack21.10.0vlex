@extends('layouts.simple')

@section('body')
    <div class="container small">
        <div class="my-s">
            @include('entities.breadcrumbs', ['crumbs' => [$parent]])
        </div>
        <main class="content-wrap card">
            <h1 class="list-heading">Nhập PDF để xem</h1>
            <p>Chọn file PDF để lưu tài liệu trong <strong>{{ $parent->name }}</strong>. Sau khi nhập, tài liệu được mở ở chế độ chỉ xem.</p>
            <form action="{{ $parent->getUrl('/import-pdf') }}" method="POST" enctype="multipart/form-data">
                {{ csrf_field() }}
                <div class="form-group">
                    <label for="pdf-page-name">Tên trang (để trống để dùng tên file)</label>
                    <input id="pdf-page-name" type="text" name="name" maxlength="255" value="{{ old('name') }}">
                    @if($errors->has('name'))<p class="text-neg">{{ $errors->first('name') }}</p>@endif
                </div>
                <div class="form-group">
                    <label for="pdf-document">Tài liệu .pdf (tối đa 20 MB)</label>
                    <input id="pdf-document" type="file" name="document" accept=".pdf,application/pdf" required>
                    @if($errors->has('document'))<p class="text-neg" role="alert">{{ $errors->first('document') }}</p>@endif
                </div>
                <p class="text-muted">Giữ nguyên file PDF gốc, gồm bố cục, ảnh và các trang scan. Không chuyển sang văn bản và không cho sửa nội dung. Khi xuất, bạn nhận lại nguyên vẹn file đã nhập. PDF có mật khẩu cần mở khóa trong trình xem; khả năng hiển thị phụ thuộc trình duyệt.</p>
                <div class="form-group text-right">
                    <a href="{{ $parent->getUrl() }}" class="button outline">Hủy</a>
                    <button type="submit" class="button">Nhập và xem PDF</button>
                </div>
            </form>
        </main>
    </div>
@stop
