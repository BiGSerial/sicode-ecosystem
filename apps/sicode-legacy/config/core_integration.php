<?php

return [
    'launch_exchange_url' => env('CORE_LAUNCH_EXCHANGE_URL'),
    'client_identifier' => env('CORE_LAUNCH_CLIENT_IDENTIFIER'),
    'client_secret' => env('CORE_LAUNCH_CLIENT_SECRET'),
    'issuer' => env('CORE_LAUNCH_ISSUER', 'sicode-core'),
    'application' => env('CORE_LAUNCH_APPLICATION', 'sicode-legacy'),
    'context' => env('CORE_LAUNCH_CONTEXT'),
];
