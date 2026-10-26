<?php

return [
    'signed_url_ttl_minutes' => 5,
    'default_download_limit_per_edition' => 3,
    'default_dowload_limit_per_day' => 10,
    'default_download_limit_per_day' => 10,
    'max_active_sessions' => 2,
    'admin_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('EPAPER_ADMIN_EMAILS', ''))
    ))),
    'storage_disk' => env('EPAPER_STORAGE_DISK', 'local'),
    'max_pdf_kilobytes' => 102400,
];
