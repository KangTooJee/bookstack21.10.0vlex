@if(signedInUser() && userCan('page-create', $importParent) && userCan('attachment-create-all'))
    <a href="{{ $importParent->getUrl('/import-pdf') }}" class="icon-list-item">
        <span>@icon('file')</span>
        <span>Nhập PDF (.pdf)</span>
    </a>
@endif
