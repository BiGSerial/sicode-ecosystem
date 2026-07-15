<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE contracts ADD CONSTRAINT contracts_status_period_check CHECK (status <> 'ended' OR ends_at IS NOT NULL)");
        DB::statement("ALTER TABLE contract_application_grants ADD CONSTRAINT contract_application_grants_status_period_check CHECK (status <> 'revoked' OR ends_at IS NOT NULL)");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE contract_application_grants DROP CONSTRAINT IF EXISTS contract_application_grants_status_period_check');
        DB::statement('ALTER TABLE contracts DROP CONSTRAINT IF EXISTS contracts_status_period_check');
    }
};
