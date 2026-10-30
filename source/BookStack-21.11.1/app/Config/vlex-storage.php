<?php

return [
    'max_bytes' => (int) env('VLEX_STORAGE_MAX_MB', 20) * 1024 * 1024,
    'extensions' => ['doc', 'docx', 'xls', 'xlsx', 'pdf'],
];
