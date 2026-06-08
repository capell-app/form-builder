<?php

declare(strict_types=1);

return [
    'store_submissions' => true,
    'collect_ip_address' => true,
    'collect_user_agent' => true,
    'throttle' => [
        'max_attempts' => 12,
        'decay_seconds' => 60,
    ],
    'spam_scoring' => [
        'enabled' => true,
        'spam_threshold' => 75,
        'max_links' => 5,
        'blocked_keywords' => [],
    ],
    'uploads' => [
        'disk' => env('CAPELL_FORM_BUILDER_UPLOAD_DISK', 'local'),
        'directory' => env('CAPELL_FORM_BUILDER_UPLOAD_DIRECTORY', 'form-builder/submissions'),
    ],
    'webhooks' => [
        'timeout_seconds' => env('CAPELL_FORM_BUILDER_WEBHOOK_TIMEOUT_SECONDS', 10),
        'allow_insecure_urls' => false,
        'allow_private_urls' => false,
    ],
];
