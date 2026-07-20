<?php

namespace Tests\Unit;

use App\Testing\LegacyDumpDatabaseGuard;
use Illuminate\Support\Facades\Config;
use RuntimeException;
use Tests\TestCase;

class LegacyDumpDatabaseGuardTest extends TestCase
{
    public function test_dump_tests_are_blocked_without_explicit_authorization_flag(): void
    {
        Config::set('legacy_testing.dump_database_allowed', false);
        Config::set('legacy_testing.allowed_connection', Config::get('database.default'));
        Config::set('legacy_testing.allowed_database', Config::get('database.connections.mysql.database'));
        Config::set('legacy_testing.allowed_hosts', [Config::get('database.connections.mysql.host')]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('LEGACY_TEST_DATABASE_ALLOWED=true');

        app(LegacyDumpDatabaseGuard::class)->assertAllowed();
    }
}
