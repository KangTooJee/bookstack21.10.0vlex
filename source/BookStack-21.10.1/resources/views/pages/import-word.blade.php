@extends('layouts.simple')

@section('body')
    <div class="container small">
        <div class="my-s">
            @include('entities.breadcrumbs', ['crumbs' => [$parent]])
        </div>
        <main class="content-wrap card">
            <h1 class="list-heading">Nhập tài liệu Word</h1>
            <p>Chọn file Word để tạo trang nháp trong <strong>{{ $parent->name }}</strong>. Bạn có thể kiểm tra và chỉnh sửa nội dung trước khi lưu trang.</p>
            <form action="{{ $parent->getUrl('/import-word') }}" method="POST" enctype="multipart/form-data">
                {{ csrf_field() }}
                <div class="form-group">
                    <label for="word-page-name">Tên trang (để trống để dùng tên file)</label>
                    <input id="word-page-name" type="text" name="name" maxlength="255" value="{{ old('name') }}">
                    @if($errors->has('name'))<p class="text-neg">{{ $errors->first('name') }}</p>@endif
                </div>
                <div class="form-group">
                    <label for="word-document">Tài liệu .doc hoặc .docx (tối đa 20 MB)</label>
                    <input id="word-document" type="file" name="document" accept=".doc,.docx" required>
                    @if($errors->has('document'))<p class="text-neg" role="alert">{{ $errors->first('document') }}</p>@endif
                </div>
                <p class="text-muted">Nội dung được chuyển thành HTML có thể chỉnh sửa, gồm văn bản, mục lục, danh sách, bảng và ảnh nhúng. Bố cục trang in, đầu/chân trang và đối tượng Word phức tạp có thể khác bản gốc. File đặt mật khẩu không được hỗ trợ.</p>
                <div class="form-group text-right">
                    <a href="{{ $parent->getUrl() }}" class="button outline">Hủy</a>
                    <button type="submit" class="button">Nhập và mở bản nháp</button>
                </div>
            </form>
        </main>
    </div>
@stop
