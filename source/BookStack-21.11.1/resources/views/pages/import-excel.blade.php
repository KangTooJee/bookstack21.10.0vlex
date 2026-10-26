@extends('layouts.simple')

@section('body')
    <div class="container small">
        <div class="my-s">
            @include('entities.breadcrumbs', ['crumbs' => [$parent]])
        </div>
        <main class="content-wrap card">
            <h1 class="list-heading">Nhập tài liệu Excel</h1>
            <p>Chọn file Excel để tạo trang nháp trong <strong>{{ $parent->name }}</strong>. Bạn có thể kiểm tra và chỉnh sửa nội dung trước khi lưu trang.</p>
            <form action="{{ $parent->getUrl('/import-excel') }}" method="POST" enctype="multipart/form-data">
                {{ csrf_field() }}
                <div class="form-group">
                    <label for="excel-page-name">Tên trang (để trống để dùng tên file)</label>
                    <input id="excel-page-name" type="text" name="name" maxlength="255" value="{{ old('name') }}">
                    @if($errors->has('name'))<p class="text-neg">{{ $errors->first('name') }}</p>@endif
                </div>
                <div class="form-group">
                    <label for="excel-document">Tài liệu .xls hoặc .xlsx (tối đa {{ \BookStack\Uploads\ExcelImportPolicy::MAX_KB }} KB, {{ \BookStack\Uploads\ExcelImportPolicy::MAX_SHEETS }} sheet tính cả sheet ẩn)</label>
                    <input id="excel-document" type="file" name="document" accept=".xls,.xlsx" data-max-bytes="{{ \BookStack\Uploads\ExcelImportPolicy::MAX_KB * 1024 }}" required>
                    @if($errors->has('document'))<p class="text-neg" role="alert">{{ $errors->first('document') }}</p>@endif
                </div>
                <p class="text-muted">Các sheet được chuyển thành bảng có thể chỉnh sửa. Công thức trở thành giá trị hiển thị; không giữ macro, pivot, bộ lọc hoặc bố cục in. File đặt mật khẩu không được hỗ trợ. Bảng tính quá lớn cần tách theo sheet hoặc vùng dữ liệu trước khi nhập.</p>
                <div class="form-group text-right">
                    <a href="{{ $parent->getUrl() }}" class="button outline">Hủy</a>
                    <button type="submit" class="button">Nhập và mở bản nháp</button>
                </div>
            </form>
        </main>
    </div>
@stop

@section('scripts')
    <script src="{{ versioned_asset('dist/excel-import.js') }}" nonce="{{ $cspNonce }}"></script>
@stop
