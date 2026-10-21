@if(signedInUser() && userCan('page-create', $importParent))
    <a href="{{ $importParent->getUrl('/import-word') }}" class="icon-list-item">
        <span>@icon('file')</span>
        <span>Nhập Word (.doc/.docx)</span>
    </a>
@endif
