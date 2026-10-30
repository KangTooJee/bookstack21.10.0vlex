@extends('layouts.simple')
@section('body')
<div class="container small">
    <div class="my-s">@include('entities.breadcrumbs', ['crumbs' => [$book, url('/files/' . $file->id . '/move') => ['text' => 'Chuyển file', 'icon' => 'folder']]])</div>
    <main class="card content-wrap">
        <h1 class="list-heading">Chuyển file</h1>
        <p><strong class="break-text">{{ $file->name }}</strong></p>
        <p class="text-muted">Vị trí hiện tại: {{ app(\BookStack\View\LibraryMap::class)->path($book) }}</p>
        <p>Tìm theo tên shelf hoặc book, sau đó chọn book đích. Bạn có thể chuyển sang book thuộc shelf khác.</p>
        <form method="post" action="{{ url('/files/' . $file->id . '/move') }}">
            {{ csrf_field() }}
            @include('entities.selector', [
                'name' => 'entity_selection', 'selectorSize' => 'large', 'entityTypes' => 'book',
                'entityPermission' => 'page-create', 'autofocus' => true,
                'selectorLabel' => 'Tìm shelf hoặc book đích',
                'selectorSearchUrl' => url('/files/' . $file->id . '/move/destinations') . '?types=book',
            ])
            @if($errors->any())<p class="text-neg" role="alert">{{ $errors->first() }}</p>@endif
            <div class="form-group text-right">
                <a href="{{ $book->getUrl() }}" class="button outline">Hủy</a>
                <button class="button">Chuyển file</button>
            </div>
        </form>
    </main>
</div>
@stop
