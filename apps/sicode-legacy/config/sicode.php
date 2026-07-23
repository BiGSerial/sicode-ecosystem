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
            // Provisioning tecnico (App\Http\Controllers\Core\ProvisioningController
            // e equivalentes) so existe hoje para SP; ES nunca deve provisionar.
            'provisioning_allowed' => false,
        ],
        'sp' => [
            'core_context' => 'SP',
            'capabilities' => [
                'ads.delivery',
            ],
            'provisioning_allowed' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Runtime isolation guard
    |--------------------------------------------------------------------------
    |
    | Fingerprint runtime esperado por unidade, usado por
    | App\Support\RuntimeIsolationGuard no boot para recusar a aplicacao
    | subir com banco, prefixo Redis, cookie de sessao ou storage de outra
    | unidade/contexto. Ver docs/standards/redis-isolation.md.
    |
    */

    'isolation' => [
        // Desligado por padrao para nao afetar `php artisan test` (que usa
        // config('sicode.unit') sintetico via configureRuntime() nos testes
        // e stores array/sync). Os containers reais SP/ES ligam via
        // SICODE_ISOLATION_GUARD_ENABLED=true no compose.yaml.
        'enabled' => env('SICODE_ISOLATION_GUARD_ENABLED', false),

        'expected_database' => env('SICODE_EXPECTED_DATABASE'),

        // Nome do banco historico (snapshot). Impede que um runtime com
        // SICODE_IDENTITY_MODE=provisioning suba apontando para esse banco.
        'snapshot_database' => env('SICODE_SNAPSHOT_DATABASE', 'sicode_legacy'),

        'redis_prefix_pattern' => 'sicode:legacy:{unit}:',
        'session_cookie_pattern' => 'sicode_{unit}_session',
        'storage_prefix_pattern' => 'legacy/{unit}',
    ],
];
