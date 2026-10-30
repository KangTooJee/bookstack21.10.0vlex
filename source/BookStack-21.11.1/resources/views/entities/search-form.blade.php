{{--
@label - Placeholder/aria-label text
--}}
<div class="mb-xl">
    <form refs="entity-search@searchForm" role="search">
        @include('search.parts.field', ['selectedScope' => 'names', 'scopeRef' => 'entity-search@scopeInput', 'inputRef' => 'entity-search@searchInput', 'inputLabel' => $label])
    </form>
</div>
