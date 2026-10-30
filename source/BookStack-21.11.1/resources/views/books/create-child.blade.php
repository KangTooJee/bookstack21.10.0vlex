@extends('layouts.simple')
@section('body')
<div class="container small">
    <div class="my-s">@include('entities.breadcrumbs', ['crumbs' => [$parentBook]])</div>
    <main class="content-wrap card">
        <h1>Tạo book con trong {{ $parentBook->name }}</h1>
        <form action="{{ $parentBook->getUrl('/create-book') }}" method="post" enctype="multipart/form-data">
            @include('books.parts.form', ['returnLocation' => $parentBook->getUrl()])
        </form>
    </main>
</div>
@stop
