@php
    $success = $result['status'] === 'stored';
    $extension = strtoupper(pathinfo($result['name'], PATHINFO_EXTENSION));
    $type = in_array($extension, ['DOC', 'DOCX']) ? 'Word' : (in_array($extension, ['XLS', 'XLSX']) ? 'Excel' : ($extension === 'PDF' ? 'PDF' : 'File'));
@endphp
<div class="vlex-upload-result vlex-upload-result--{{ $success ? 'success' : 'error' }}" role="listitem">
    <span class="vlex-upload-result__icon" aria-hidden="true">{{ $success ? '✓' : '×' }}</span>
    <div class="vlex-upload-result__content">
        <strong class="vlex-upload-result__name">{{ $result['name'] }}</strong>
        <span class="vlex-upload-result__type">{{ $type }}{{ $extension && strtoupper($type) !== $extension ? ' · ' . $extension : '' }}</span>
        @unless($success)
            <ul class="vlex-upload-result__errors">@foreach($result['errors'] as $message)<li>{{ $message }}</li>@endforeach</ul>
        @endunless
    </div>
    <span class="vlex-upload-result__status">{{ $success ? 'Đã lưu' : 'Thất bại' }}</span>
</div>
