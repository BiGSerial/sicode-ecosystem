<?php

namespace Tests\Concerns;

use App\Testing\LegacyDumpDatabaseGuard;
use Illuminate\Foundation\Testing\DatabaseTransactions;

trait UsesRestoredLegacyDatabase
{
    use DatabaseTransactions;

    protected function setUpUsesRestoredLegacyDatabase(): void
    {
        app(LegacyDumpDatabaseGuard::class)->assertAllowed();
    }
}
