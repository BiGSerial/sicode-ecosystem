<?php

namespace App\Testing;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use RuntimeException;

final class LegacyDumpDatabaseGuard
{
    public function assertAllowed(): void
    {
        $connection = (string) Config::get('database.default');
        $database = (string) Config::get("database.connections.{$connection}.database");
        $host = (string) Config::get("database.connections.{$connection}.host");

        if (! App::environment('testing')) {
            throw new RuntimeException('Legacy dump tests are allowed only with APP_ENV=testing.');
        }

        if (! (bool) Config::get('legacy_testing.dump_database_allowed')) {
            throw new RuntimeException('Legacy dump tests require LEGACY_TEST_DATABASE_ALLOWED=true.');
        }

        if ($connection !== (string) Config::get('legacy_testing.allowed_connection')) {
            throw new RuntimeException('Legacy dump tests are not allowed on the configured database connection.');
        }

        if ($database !== (string) Config::get('legacy_testing.allowed_database')) {
            throw new RuntimeException('Legacy dump tests are not allowed on the configured database name.');
        }

        if (! in_array($host, (array) Config::get('legacy_testing.allowed_hosts'), true)) {
            throw new RuntimeException('Legacy dump tests are not allowed on the configured database host.');
        }

        if (str_contains(strtolower($database), 'prod') || str_contains(strtolower($host), 'prod')) {
            throw new RuntimeException('Legacy dump tests refused a production-like database configuration.');
        }
    }
}
