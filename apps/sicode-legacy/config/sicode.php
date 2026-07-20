<?php

return [
    'unit' => env('SICODE_UNIT'),
    'identity_mode' => env('SICODE_IDENTITY_MODE', 'reconciliation'),

    'instance' => [
        'code' => env('SICODE_INSTANCE_CODE'),
        'name' => env('SICODE_INSTANCE_NAME', 'SICODE Legacy'),
    ],

    'core' => [
        'expected_context' => env('CORE_LAUNCH_CONTEXT'),
        'client' => [
            'identifier' => env('CORE_LAUNCH_CLIENT_IDENTIFIER'),
            'secret' => env('CORE_LAUNCH_CLIENT_SECRET'),
            'redirect_uri' => env('CORE_LAUNCH_REDIRECT_URI'),
        ],
    ],

    'storage' => [
        'disk' => env('FILESYSTEM_DISK', 'local'),
        'root' => env('SICODE_STORAGE_ROOT'),
        'prefix' => env('SICODE_STORAGE_PREFIX'),
    ],

    'units' => [
        'es' => [
            'core_context' => 'ES',
            'capabilities' => [
                'ads.delivery',
                'legacy.local_login',
                'production.contract_company_fallback',
                'work_report.tacit_approval',
            ],
        ],
        'sp' => [
            'core_context' => 'SP',
            'capabilities' => [
                'ads.delivery',
            ],
        ],
    ],
];
