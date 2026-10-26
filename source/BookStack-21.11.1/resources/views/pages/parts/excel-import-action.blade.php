@if(signedInUser() && userCan('page-create', $importParent))
    <a href="{{ $importParent->getUrl('/import-excel') }}" class="icon-list-item">
        <span>@icon('file')</span>
        <span>Nhập Excel (.xls/.xlsx)</span>
    </a>
@endif
