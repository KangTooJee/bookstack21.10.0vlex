<div component="dropdown" class="dropdown-container" id="export-menu">
    <div refs="dropdown@toggle" class="icon-list-item"
         aria-haspopup="true" aria-expanded="false" aria-label="{{ trans('entities.export') }}" tabindex="0">
        <span>@icon('export')</span>
        <span>{{ trans('entities.export') }}</span>
    </div>
    <ul refs="dropdown@menu" class="wide dropdown-menu" role="menu">
        @if($entity->isA('page') && $entity->isPdf())
            <li><a href="{{ $entity->getUrl('/export/pdf') }}">Tải PDF gốc <span class="text-muted float right">.pdf</span></a></li>
        @else
        @if($entity->isA('page'))
            <li><a href="{{ $entity->getUrl('/export/excel') }}">{{ trans('entities.export_excel') }} <span class="text-muted float right">.xlsx</span></a></li>
            <li><a href="{{ $entity->getUrl('/export/word') }}">{{ trans('entities.export_word') }} <span class="text-muted float right">.docx</span></a></li>
        @endif
        <li><a href="{{ $entity->getUrl('/export/html') }}" target="_blank" rel="noopener">{{ trans('entities.export_html') }} <span class="text-muted float right">.html</span></a></li>
        <li><a href="{{ $entity->getUrl('/export/pdf') }}" target="_blank" rel="noopener">{{ trans('entities.export_pdf') }} <span class="text-muted float right">.pdf</span></a></li>
        <li><a href="{{ $entity->getUrl('/export/plaintext') }}" target="_blank" rel="noopener">{{ trans('entities.export_text') }} <span class="text-muted float right">.txt</span></a></li>
        <li><a href="{{ $entity->getUrl('/export/markdown') }}" target="_blank" rel="noopener">{{ trans('entities.export_md') }} <span class="text-muted float right">.md</span></a></li>
        @endif
    </ul>
</div>
