@extends('layouts.base')
@section('body-class', 'vlex-workspace')
@section('head')
<link rel="stylesheet" href="{{ url('/dist/vlex-workspace.css') }}">
@stop
@section('content')
<div class="vlex-bar">
    <a class="vlex-brand" href="{{ url('/vlex') }}"><span>▱</span> VLEX</a>
    <form class="vlex-search" action="{{ url('/vlex') }}"><input aria-label="Tìm tài liệu toàn bộ thư mục" name="q" placeholder="Search · Tìm thư mục, tài liệu…" value="{{ request('q') }}"><button aria-label="Tìm kiếm">⌕</button></form>
    <nav><a href="{{ url('/books') }}">◇ Projects / BookStack</a><a href="{{ url('/settings') }}">⚙ Settings</a><span>● {{ user()->name }}</span></nav>
</div>
<div class="vlex-layout">
    <aside class="vlex-tree">
        <form class="folder-search" action="{{ url('/vlex') }}"><input name="q" aria-label="Tìm trong kho tài liệu" value="{{ request('q') }}" placeholder="Search this folder"><button aria-label="Tìm kiếm">⌕</button></form>
        <div class="tree-shortcuts"><a href="{{ url('/vlex') }}">▱ Tất cả thư mục</a><a href="{{ url('/vlex?mode=favourites') }}">♡ Yêu thích</a><a href="{{ url('/vlex?mode=trash') }}">♲ Thùng rác</a></div>
        <div class="tree-label">THƯ MỤC TÀI LIỆU</div>
        @include('vlex.tree', ['treeParent' => ''])
        <p class="tree-note">VLEX · Môi trường mô phỏng<br>BookStack v21.11.1</p>
    </aside>
    <main class="vlex-main" id="main-content">
        <nav class="vlex-crumb"><a href="{{ url('/vlex') }}">VLEX</a>@foreach($crumbs as $crumb)<span>›</span><a href="{{ url('/vlex?node='.$crumb->id) }}">{{ $crumb->name }}</a>@endforeach</nav>
        @if($errors->any())<div class="vlex-alert" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <div class="workspace-title"><div><div class="eyebrow">DOCUMENT WORKSPACE</div><h1>{{ request('q') ? 'Kết quả tìm kiếm' : ($mode === 'trash' ? 'Thùng rác' : ($mode === 'favourites' ? 'Tài liệu yêu thích' : ($current->name ?? 'Kho tài liệu VLEX'))) }}</h1></div><span class="workspace-badge">NỘI BỘ · LOCAL</span></div>
        @if($current && $current->kind === 'page')
            <article class="vlex-paper"><div class="paper-caption">TRANG NỘI DUNG · VLEX</div><h2>{{ $current->name }}</h2><div class="page-text">{{ $current->content }}</div></article>
        @elseif($current && $current->kind === 'file')
            @php $latest = $versions->first(); $extension = $latest ? strtolower(pathinfo($latest->original_name, PATHINFO_EXTENSION)) : ''; @endphp
            <section class="file-preview">
                <div class="file-hero"><span class="file-symbol {{ in_array($extension, ['xls','xlsx','xlsm','xlsb','csv']) ? 'excel' : ($extension === 'pdf' ? 'pdf' : '') }}">{{ strtoupper($extension) }}</span><div><h2>{{ $current->name }}</h2><p>{{ $latest ? number_format($latest->size / 1024, 1) : 0 }} KB · {{ count($versions) }} phiên bản</p></div></div>
                @if($extension === 'pdf')
                    <iframe title="Xem tài liệu PDF" src="{{ url('/vlex/files/'.$current->id.'?preview=1') }}"></iframe>
                @else
                    <div class="office-message"><h3>Tài liệu được lưu nguyên bản</h3><p>Tải xuống để mở và chỉnh sửa bằng Microsoft Office hoặc phần mềm tương thích trên máy. Sau khi lưu, chọn “Tải phiên bản mới” để giữ lịch sử.</p><p>Để chuyển Word thành trang có định dạng, mở sách hoặc chương trong BookStack và chọn “Nhập Word”.</p><a class="vlex-button" href="{{ url('/vlex/files/'.$current->id) }}">↓ Tải tài liệu</a></div>
                @endif
                <h3 class="history-title">Lịch sử phiên bản</h3>
                @foreach($versions as $version)<div class="version-row"><span>v{{ count($versions) - $loop->index }} · {{ $version->original_name }}<small>{{ $version->created_at }} · {{ number_format($version->size / 1024, 1) }} KB</small></span><a href="{{ url('/vlex/files/'.$current->id.'?version='.$version->id) }}">Tải xuống ↓</a></div>@endforeach
            </section>
        @else
            <div class="content-toolbar"><span>{{ $items->total() }} mục{{ request('q') ? ' phù hợp với “'.request('q').'”' : '' }}</span><div><a class="{{ request('view') !== 'list' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['view'=>'grid']) }}">▦ Lưới</a><a class="{{ request('view') === 'list' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['view'=>'list']) }}">☰ Danh sách</a></div></div>
            <section class="vlex-items {{ request('view') === 'list' || $mode === 'trash' ? 'list-view' : '' }}">
                @foreach($items as $item)
                    @php $ext = strtolower(pathinfo($item->name, PATHINFO_EXTENSION)); @endphp
                    <div class="vlex-item {{ $item->kind }}">
                        @if($mode !== 'trash')<a class="item-main" href="{{ url('/vlex?node='.$item->id) }}">@else<div class="item-main">@endif
                            <div class="item-cover {{ in_array($ext,['xls','xlsx','xlsm','csv']) ? 'excel' : ($ext === 'pdf' ? 'pdf' : '') }}"><span>{{ $item->kind === 'folder' ? '▱' : ($item->kind === 'page' ? '▤' : strtoupper($ext)) }}</span></div>
                            <div class="item-info"><strong>{{ $item->name }}</strong><small>{{ $item->kind === 'folder' ? 'Thư mục' : ($item->kind === 'page' ? 'Trang nội dung' : 'Tài liệu') }}{{ $item->favourite ? ' · ♥' : '' }}</small><small>Cập nhật {{ substr($item->updated_at, 0, 16) }}</small></div>
                        @if($mode !== 'trash')</a>@else</div>@endif
                        @if($mode === 'trash')<form method="post" action="{{ url('/vlex/nodes/'.$item->id) }}">@csrf<input type="hidden" name="action" value="restore"><button class="restore-button">Khôi phục</button></form>@endif
                    </div>
                @endforeach
            </section>
            @if($items->isEmpty())<div class="vlex-empty"><span>▱</span><h2>{{ request('q') ? 'Không tìm thấy tài liệu' : 'Chưa có tài liệu trong thư mục này' }}</h2><p>Tạo thư mục con, viết trang nội dung hoặc tải tài liệu Word, Excel, PDF lên để bắt đầu.</p>@if(!$current && $mode === 'folder' && !request('q') && $folders->isEmpty())<form method="post" action="{{ url('/vlex/demo') }}">@csrf<button class="vlex-button">Khởi tạo cây thư mục mẫu theo ảnh VLEX</button></form>@endif</div>@endif
            <div class="vlex-pagination">{{ $items->links() }}</div>
        @endif
    </main>
    <aside class="vlex-actions">
        <h3>Details</h3><div class="detail-lines"><p>♙ {{ user()->name }}</p><p>◷ {{ $current->updated_at ?? 'Kho tài liệu cá nhân' }}</p><p>▣ {{ $current ? ['folder'=>'Thư mục','page'=>'Trang nội dung','file'=>'Tài liệu'][$current->kind] : 'Workspace' }}</p></div>
        <h3>Actions</h3>
        @if($mode !== 'trash')
        <button class="action-link" data-dialog="create-folder"><span class="action-icon blue">＋</span>New Folder</button>
        <button class="action-link" data-dialog="create-page"><span class="action-icon blue">＋</span>New Page</button>
        <button class="action-link" data-dialog="upload"><span class="action-icon blue">↑</span>Upload File</button>
        <a class="action-link" href="{{ request()->fullUrlWithQuery(['view'=>request('view') === 'list' ? 'grid' : 'list']) }}"><span class="action-icon peach">☰</span>{{ request('view') === 'list' ? 'Grid View' : 'List View' }}</a>
        @if($current)
        <hr>
        <button class="action-link" data-dialog="edit"><span class="action-icon peach">✎</span>Edit</button>
        <button class="action-link" data-dialog="move"><span class="action-icon peach">▱</span>Move</button>
        <button class="action-link" data-dialog="permissions"><span class="action-icon peach">♙</span>Permissions</button>
        <button class="action-link" data-dialog="trash"><span class="action-icon coral">♲</span>Delete</button>
        <hr>
        <form method="post" action="{{ url('/vlex/nodes/'.$current->id) }}">@csrf<input type="hidden" name="action" value="favourite"><button class="action-link"><span class="action-icon coral">♥</span>{{ $current->favourite ? 'Unfavourite' : 'Favourite' }}</button></form>
        @if($current->kind === 'file')<a class="action-link" href="{{ url('/vlex/files/'.$current->id) }}"><span class="action-icon peach">↓</span>Download</a><button class="action-link" data-dialog="new-version"><span class="action-icon blue">↑</span>Tải phiên bản mới</button>@endif
        @endif
        @endif
        <div class="scope-note">Lưu trữ Word / Excel / PDF<br>Tối đa 20 MB mỗi tệp<br>Chỉ tài khoản của bạn truy cập</div>
    </aside>
</div>
@foreach(['folder'=>'Tạo thư mục mới','page'=>'Tạo trang nội dung'] as $kind=>$label)
<dialog id="create-{{ $kind }}"><form method="post" action="{{ url('/vlex/nodes') }}">@csrf<h2>{{ $label }}</h2><input type="hidden" name="kind" value="{{ $kind }}"><input type="hidden" name="parent_id" value="{{ $parent }}"><label>Tên<input name="name" required maxlength="255"></label>@if($kind === 'page')<label>Nội dung<textarea name="content" rows="12"></textarea></label>@endif<div class="dialog-buttons"><button type="button" data-close>Hủy</button><button class="vlex-button">Tạo mới</button></div></form></dialog>
@endforeach
<dialog id="upload"><form method="post" enctype="multipart/form-data" action="{{ url('/vlex/upload') }}">@csrf<h2>Tải tài liệu lên</h2><p>Giữ nguyên tệp gốc · Tối đa 20 MB</p><input type="hidden" name="parent_id" value="{{ $parent }}"><label>Chọn tài liệu<input type="file" name="document" required accept=".doc,.docx,.docm,.dot,.dotx,.dotm,.xls,.xlsx,.xlsm,.xlsb,.xlt,.xltx,.xltm,.csv,.pdf,.rtf,.odt,.ods"></label><div class="dialog-buttons"><button type="button" data-close>Hủy</button><button class="vlex-button">Tải lên</button></div></form></dialog>
@if($current)
<dialog id="edit"><form method="post" action="{{ url('/vlex/nodes/'.$current->id) }}">@csrf<h2>Chỉnh sửa</h2><input type="hidden" name="action" value="edit"><label>Tên<input name="name" value="{{ $current->name }}" required maxlength="255"></label>@if($current->kind === 'page')<label>Nội dung (văn bản thuần)<textarea name="content" rows="16">{{ $current->content }}</textarea></label>@endif<div class="dialog-buttons"><button type="button" data-close>Hủy</button><button class="vlex-button">Lưu thay đổi</button></div></form></dialog>
<dialog id="move"><form method="post" action="{{ url('/vlex/nodes/'.$current->id) }}">@csrf<h2>Di chuyển {{ $current->name }}</h2><input type="hidden" name="action" value="move"><label>Thư mục đích<select name="parent_id"><option value="">VLEX / Thư mục gốc</option>@foreach($folders as $folder)<option value="{{ $folder->id }}" {{ $folder->id == $current->parent_id ? 'selected' : '' }}>{{ $folder->name }} (#{{ $folder->id }})</option>@endforeach</select></label><div class="dialog-buttons"><button type="button" data-close>Hủy</button><button class="vlex-button">Di chuyển</button></div></form></dialog>
<dialog id="permissions"><h2>Quyền truy cập</h2><p>Chủ sở hữu: <strong>{{ user()->name }}</strong></p><p>Kho mô phỏng hiện tách dữ liệu theo tài khoản. Chỉ chủ sở hữu có thể xem, tải xuống và thay đổi các mục này. Chia sẻ nhóm và kế thừa quyền chi tiết chưa được tích hợp vào Workspace.</p><div class="dialog-buttons"><button class="vlex-button" data-close>Đóng</button></div></dialog>
<dialog id="trash"><form method="post" action="{{ url('/vlex/nodes/'.$current->id) }}">@csrf<h2>Đưa vào thùng rác?</h2><p>“{{ $current->name }}” và các mục con sẽ được đưa vào thùng rác. Bạn có thể khôi phục sau.</p><input type="hidden" name="action" value="trash"><div class="dialog-buttons"><button type="button" data-close>Hủy</button><button class="vlex-button">Đưa vào thùng rác</button></div></form></dialog>
<dialog id="new-version"><form method="post" enctype="multipart/form-data" action="{{ url('/vlex/upload') }}">@csrf<h2>Tải phiên bản mới</h2><p>Phiên bản trước vẫn được giữ trong lịch sử.</p><input type="hidden" name="node_id" value="{{ $current->id }}"><label>Tài liệu thay thế<input type="file" name="document" required></label><div class="dialog-buttons"><button type="button" data-close>Hủy</button><button class="vlex-button">Lưu phiên bản</button></div></form></dialog>
@endif
@stop
@section('scripts')
<script nonce="{{ $cspNonce }}">
document.querySelectorAll('[data-dialog]').forEach(button => button.addEventListener('click', () => document.getElementById(button.dataset.dialog).showModal()));
document.querySelectorAll('[data-close]').forEach(button => button.addEventListener('click', () => button.closest('dialog').close()));
</script>
@stop
