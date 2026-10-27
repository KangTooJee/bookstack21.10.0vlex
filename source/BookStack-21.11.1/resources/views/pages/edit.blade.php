@extends('layouts.base')

@php($excelContent = \BookStack\Uploads\ExcelPageLayout::matches($page->html))

@section('head')
    <script src="{{ url('/libs/tinymce/tinymce.min.js?ver=4.9.4') }}" nonce="{{ $cspNonce }}"></script>
    @if($excelContent)
        <link rel="stylesheet" href="{{ versioned_asset('dist/excel-editor.css') }}">
        <script src="{{ versioned_asset('dist/excel-editor.js') }}" data-content-css="{{ versioned_asset('dist/excel-editor.css') }}" nonce="{{ $cspNonce }}"></script>
    @endif
@stop

@section('body-class', $excelContent ? 'flexbox excel-edit-page' : 'flexbox')

@section('content')

    <div id="main-content" class="flex-fill flex fill-height">
        <form action="{{ $page->getUrl() }}" autocomplete="off" data-page-id="{{ $page->id }}" method="POST" class="flex flex-fill">
            {{ csrf_field() }}

            @if(!isset($isDraft))
                <input type="hidden" name="_method" value="PUT">
            @endif
            @include('pages.parts.form', ['model' => $page])
            @include('pages.parts.editor-toolbox')
        </form>
    </div>
    
    @include('pages.parts.image-manager', ['uploaded_to' => $page->id])
    @include('pages.parts.code-editor')
    @include('entities.selector-popup')
@stop
