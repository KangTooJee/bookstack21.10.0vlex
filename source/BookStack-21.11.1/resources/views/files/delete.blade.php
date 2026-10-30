@extends('layouts.simple')
@section('body')
<div class="container small">
    <div class="my-s">@include('entities.breadcrumbs', ['crumbs' => [$book, url('/files/' . $file->id . '/delete') => ['text' => 'Xóa file', 'icon' => 'delete']]])</div>
    <main class="card content-wrap auto-height">
        <h1 class="list-heading">Xóa file</h1>
        <p><strong class="break-text">{{ $file->name }}</strong></p>
        <p class="text-muted">Vị trí: {{ app(\BookStack\View\LibraryMap::class)->path($book) }}</p>
        <p class="text-neg">Bạn có chắc muốn xóa vĩnh viễn file này? Không thể khôi phục sau khi xóa.</p>
        <form method="post" action="{{ url('/files/' . $file->id) }}">
            {{ csrf_field() }} {{ method_field('DELETE') }}
            <div class="form-group text-right">
                <a href="{{ $book->getUrl() }}" class="button outline">Hủy</a>
                <button class="button">Xác nhận xóa</button>
            </div>
        </form>
    </main>
</div>
@stop
