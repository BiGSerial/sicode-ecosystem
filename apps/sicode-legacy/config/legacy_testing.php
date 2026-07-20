<?php

return [
    'dump_database_allowed' => env('LEGACY_TEST_DATABASE_ALLOWED', false),
    'allowed_database' => env('LEGACY_TEST_DATABASE_NAME', 'sicode_legacy'),
    'allowed_connection' => env('LEGACY_TEST_DATABASE_CONNECTION', 'mysql'),
    'allowed_hosts' => array_filter(array_map('trim', explode(',', env('LEGACY_TEST_DATABASE_HOSTS', 'sicode-legacy-mariadb,127.0.0.1,localhost')))),
];
