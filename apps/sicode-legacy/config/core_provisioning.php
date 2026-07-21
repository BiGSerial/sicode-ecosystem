<?php

$clientSecrets = json_decode((string) env('CORE_PROVISIONING_CLIENT_SECRETS', '{}'), true);

return [
    'contract_version' => env('CORE_PROVISIONING_CONTRACT_VERSION', '2026-07-21'),
    'request_timeout_seconds' => (int) env('CORE_PROVISIONING_REQUEST_TIMEOUT_SECONDS', 5),
    'lock_timeout_seconds' => (int) env('CORE_PROVISIONING_LOCK_TIMEOUT_SECONDS', 5),
    'rate_limit_per_minute' => (int) env('CORE_PROVISIONING_RATE_LIMIT_PER_MINUTE', 30),
    'client_secrets' => is_array($clientSecrets) ? $clientSecrets : [],
    'browser_block' => (bool) env('CORE_PROVISIONING_BLOCK_BROWSER_REQUESTS', true),
    'placeholder_email_domain' => env('CORE_PROVISIONING_PLACEHOLDER_EMAIL_DOMAIN', 'provisioning.local'),
];
