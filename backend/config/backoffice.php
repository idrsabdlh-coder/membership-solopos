<?php

return [
    'admin_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('BACKOFFICE_ADMIN_EMAILS', ''))
    ))),
    'staff_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('BACKOFFICE_STAFF_EMAILS', ''))
    ))),
];
