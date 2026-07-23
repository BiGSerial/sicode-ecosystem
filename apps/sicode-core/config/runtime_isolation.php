<?php

/*
|--------------------------------------------------------------------------
| CORE Runtime Isolation Fingerprint
|--------------------------------------------------------------------------
|
| Fingerprint runtime esperado do CORE, usado por
| App\Support\CoreRuntimeIsolationGuard no boot para recusar a aplicacao
| subir com prefixo Redis, database Redis, cookie de sessao, issuer ou URL
| divergentes do runtime CORE global, ou vazando para o namespace Legacy.
|
| Ao contrario de config/sicode.php no Legacy (que descreve multiplas
| unidades ES/SP), este arquivo descreve uma unica aplicacao global — por
| isso o nome deliberadamente diferente, para nao ser confundido com a
| config multiunidade do Legacy.
|
| Ver docs/standards/redis-isolation.md.
|
*/

return [

    'enabled' => env('SICODE_CORE_ISOLATION_GUARD_ENABLED', false),

    'redis_prefix' => env('SICODE_CORE_EXPECTED_REDIS_PREFIX', 'sicode:core:global:'),
    'forbidden_redis_prefix' => 'sicode:legacy:',

    // Fingerprint exato (database + prefixo) por conexao Redis nomeada.
    // O guard rejeita divergencia de qualquer um dos dois valores, nao so
    // a presenca do database numa lista solta de valores permitidos.
    'connections' => [
        'default' => [
            'database' => (int) env('SICODE_CORE_EXPECTED_REDIS_DB', 12),
            'purpose' => 'lock',
        ],
        'cache' => [
            'database' => (int) env('SICODE_CORE_EXPECTED_REDIS_CACHE_DB', 13),
            'purpose' => 'cache',
        ],
        'redis_session' => [
            'database' => (int) env('SICODE_CORE_EXPECTED_REDIS_SESSION_DB', 14),
            'purpose' => 'session',
        ],
        'queue' => [
            'database' => (int) env('SICODE_CORE_EXPECTED_REDIS_QUEUE_DB', 15),
            'purpose' => 'queue',
        ],
    ],

    'session_cookie' => env('SICODE_CORE_EXPECTED_SESSION_COOKIE', 'sicode_core_session'),

    // Cookies de unidade Legacy que o CORE nunca deve reutilizar.
    'forbidden_session_cookie_pattern' => '/^sicode_(es|sp|snapshot)_session$/',

    'issuer' => env('SICODE_CORE_EXPECTED_ISSUER', 'sicode-core'),

    // Vazio = so valida que app.env nao esta vazio, sem exigir um valor exato.
    'app_env' => env('SICODE_CORE_EXPECTED_APP_ENV'),

    // Vazio = so valida que app.url nao esta vazio, sem exigir um valor exato.
    'app_url' => env('SICODE_CORE_EXPECTED_APP_URL'),

];
