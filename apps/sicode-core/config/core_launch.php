<?php

declare(strict_types=1);

$clientSecrets = json_decode((string) env('CORE_LAUNCH_CLIENT_SECRETS', '{}'), true);

return [
    'issuer' => env('CORE_LAUNCH_ISSUER', 'sicode-core'),
    'ttl_seconds' => (int) env('CORE_LAUNCH_TTL_SECONDS', 300),
    'client_secrets' => is_array($clientSecrets) ? $clientSecrets : [],
];
