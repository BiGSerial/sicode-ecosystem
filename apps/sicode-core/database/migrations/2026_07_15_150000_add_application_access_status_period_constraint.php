<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE application_accesses ADD CONSTRAINT application_accesses_status_period_check CHECK (status <> 'revoked' OR ends_at IS NOT NULL)");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE application_accesses DROP CONSTRAINT IF EXISTS application_accesses_status_period_check');
    }
};
