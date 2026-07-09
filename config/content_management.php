<?php

return [
    'xlsx_import' => [
        'temp_disk' => env('CONTENT_XLSX_TEMP_DISK', 'local'),
        'temp_file_ttl_minutes' => (int) env('CONTENT_XLSX_TEMP_FILE_TTL_MINUTES', 180),
        'prune_schedule_cron' => env('CONTENT_XLSX_PRUNE_CRON', '20 3 * * *'),
    ],
    'final_files' => [
        'disk' => env('CONTENT_FINAL_FILES_DISK', 'local'),
        'base_dir' => env('CONTENT_FINAL_FILES_BASE_DIR', 'content-management/final-files'),
        'max_file_kb' => (int) env('CONTENT_FINAL_FILES_MAX_FILE_KB', 10240),
    ],
];
