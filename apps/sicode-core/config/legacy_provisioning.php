<?php

declare(strict_types=1);

return [
    'sp' => [
        'enabled' => (bool) env('LEGACY_SP_PROVISIONING_ENABLED', false),
        'base_url' => env('LEGACY_SP_PROVISIONING_BASE_URL'),
        'client_identifier' => env('LEGACY_SP_PROVISIONING_CLIENT_ID'),
        'client_secret' => env('LEGACY_SP_PROVISIONING_CLIENT_SECRET'),
        'issuer' => env('LEGACY_SP_PROVISIONING_ISSUER', env('CORE_LAUNCH_ISSUER', 'sicode-core')),
        'contract_version' => env('LEGACY_SP_PROVISIONING_CONTRACT_VERSION', '2026-07-21'),
        'expected_context' => env('LEGACY_SP_PROVISIONING_CONTEXT', 'sp'),
        'connect_timeout_seconds' => (float) env('LEGACY_SP_PROVISIONING_CONNECT_TIMEOUT_SECONDS', 2.0),
        'timeout_seconds' => (float) env('LEGACY_SP_PROVISIONING_TIMEOUT_SECONDS', 8.0),
        'max_response_bytes' => (int) env('LEGACY_SP_PROVISIONING_MAX_RESPONSE_BYTES', 65536),
        'retry' => [
            'max_attempts' => (int) env('LEGACY_SP_PROVISIONING_RETRY_MAX_ATTEMPTS', 3),
            'backoff_milliseconds' => (int) env('LEGACY_SP_PROVISIONING_RETRY_BACKOFF_MS', 150),
            'jitter_milliseconds' => (int) env('LEGACY_SP_PROVISIONING_RETRY_JITTER_MS', 50),
            'max_retry_after_seconds' => (int) env('LEGACY_SP_PROVISIONING_MAX_RETRY_AFTER_SECONDS', 5),
        ],
    ],
];
