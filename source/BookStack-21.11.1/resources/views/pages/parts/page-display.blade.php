<div dir="auto">

    <h1 class="break-text" id="bkmrk-page-title">{{$page->name}}</h1>

    <div style="clear:left;"></div>

    @if (isset($diff) && $diff)
        {!! $diff !!}
    @else
        {!! \BookStack\Uploads\ExcelHtmlLayout::prepare(isset($page->renderedHTML) ? $page->renderedHTML : $page->html) !!}
    @endif
</div>
