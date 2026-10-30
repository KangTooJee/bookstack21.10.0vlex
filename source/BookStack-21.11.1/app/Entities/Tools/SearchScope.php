<?php

namespace BookStack\Entities\Tools;

class SearchScope
{
    public const LABELS = [
        'names' => 'Tìm kiếm theo tên folder/file',
        'word' => 'Tìm kiếm theo tên/nội dung file Word',
        'excel' => 'Tìm kiếm theo tên/nội dung file Excel',
        'pdf' => 'Tìm kiếm PDF theo tiêu đề',
    ];

    public static function rules(): array
    {
        return ['required', 'string', 'in:' . implode(',', array_keys(self::LABELS))];
    }
}
