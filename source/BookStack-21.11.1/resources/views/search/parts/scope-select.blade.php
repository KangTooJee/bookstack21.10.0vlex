<select name="scope" required aria-label="Phạm vi tìm kiếm"
        @if(!empty($scopeRef)) refs="{{ $scopeRef }}" @endif>
    @foreach((!empty($expandedScope)
        ? ['names' => 'Tên folder/file', 'word' => 'Tên và nội dung file Word', 'excel' => 'Tên và nội dung file Excel', 'pdf' => 'PDF — chỉ tìm tiêu đề']
        : ['names' => 'Name', 'word' => 'Word', 'excel' => 'Excel', 'pdf' => 'PDF']) as $value => $label)
        <option value="{{ $value }}" @if($value === 'pdf') title="PDF chỉ hỗ trợ tìm theo tiêu đề, không tìm nội dung bên trong file." @endif @if(($selectedScope ?? 'names') === $value) selected @endif>{{ $label }}</option>
    @endforeach
</select>
