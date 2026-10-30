@extends('layouts.simple')
@section('body')
<div class="container small">
    <div class="my-s">@include('entities.breadcrumbs', ['crumbs' => [$book]])</div>
    <main class="content-wrap card">
        <h1>Chuyển book {{ $book->name }}</h1>
        <p>Toàn bộ book con, page và file đi cùng book này. Liên kết tới vị trí cũ được gỡ bỏ.</p>
        <form action="{{ $book->getUrl('/move-folder') }}" method="post">
            {{ csrf_field() }}
            <label for="destination">Shelf hoặc book đích</label>
            <select id="destination" name="destination" required>
                @foreach($shelves as $target)<option value="shelf:{{ $target->id }}">Shelf: {{ $target->name }} (#{{ $target->id }})</option>@endforeach
                @foreach($books as $target)<option value="book:{{ $target->id }}">{{ $hierarchy->path($target) }} (#{{ $target->id }})</option>@endforeach
            </select>
            @if($errors->has('destination'))<p role="alert" class="text-neg">{{ $errors->first('destination') }}</p>@endif
            <div class="form-group text-right"><a class="button outline" href="{{ $book->getUrl() }}">Hủy</a><button class="button">Chuyển book</button></div>
        </form>
    </main>
</div>
@stop
