<?php

use App\CoreAudit\CoreAuditAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE contracts DROP CONSTRAINT IF EXISTS contracts_status_check');
        DB::statement("ALTER TABLE contracts ADD CONSTRAINT contracts_status_check CHECK (status IN ('draft', 'active', 'suspended', 'ended', 'expired'))");

        DB::statement('ALTER TABLE contracts DROP CONSTRAINT IF EXISTS contracts_status_period_check');
        DB::statement("ALTER TABLE contracts ADD CONSTRAINT contracts_status_period_check CHECK (status NOT IN ('ended', 'expired') OR ends_at IS NOT NULL)");

        DB::statement('ALTER TABLE application_accesses DROP CONSTRAINT IF EXISTS application_accesses_status_check');
        DB::statement("ALTER TABLE application_accesses ADD CONSTRAINT application_accesses_status_check CHECK (status IN ('active', 'suspended', 'revoked', 'expired'))");

        DB::statement('ALTER TABLE application_accesses DROP CONSTRAINT IF EXISTS application_accesses_status_period_check');
        DB::statement("ALTER TABLE application_accesses ADD CONSTRAINT application_accesses_status_period_check CHECK (status NOT IN ('revoked', 'expired') OR ends_at IS NOT NULL)");

        DB::statement('ALTER TABLE core_audit_events DROP CONSTRAINT IF EXISTS core_audit_events_action_check');
        DB::statement('ALTER TABLE core_audit_events ADD CONSTRAINT core_audit_events_action_check CHECK (action IN ('.$this->quotedSqlList(CoreAuditAction::values()).'))');

        DB::statement("CREATE INDEX IF NOT EXISTS contracts_active_expiration_idx ON contracts (status, ends_at) WHERE status = 'active' AND ends_at IS NOT NULL");
        DB::statement("CREATE INDEX IF NOT EXISTS application_accesses_active_expiration_idx ON application_accesses (status, ends_at) WHERE status = 'active' AND ends_at IS NOT NULL");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS contracts_active_expiration_idx');
        DB::statement('DROP INDEX IF EXISTS application_accesses_active_expiration_idx');

        DB::statement('ALTER TABLE contracts DROP CONSTRAINT IF EXISTS contracts_status_period_check');
        DB::statement("ALTER TABLE contracts ADD CONSTRAINT contracts_status_period_check CHECK (status <> 'ended' OR ends_at IS NOT NULL)");

        DB::statement('ALTER TABLE contracts DROP CONSTRAINT IF EXISTS contracts_status_check');
        DB::statement("ALTER TABLE contracts ADD CONSTRAINT contracts_status_check CHECK (status IN ('draft', 'active', 'suspended', 'ended'))");

        DB::statement('ALTER TABLE application_accesses DROP CONSTRAINT IF EXISTS application_accesses_status_period_check');
        DB::statement("ALTER TABLE application_accesses ADD CONSTRAINT application_accesses_status_period_check CHECK (status <> 'revoked' OR ends_at IS NOT NULL)");

        DB::statement('ALTER TABLE application_accesses DROP CONSTRAINT IF EXISTS application_accesses_status_check');
        DB::statement("ALTER TABLE application_accesses ADD CONSTRAINT application_accesses_status_check CHECK (status IN ('active', 'suspended', 'revoked'))");

        DB::statement('ALTER TABLE core_audit_events DROP CONSTRAINT IF EXISTS core_audit_events_action_check');
        DB::statement('ALTER TABLE core_audit_events ADD CONSTRAINT core_audit_events_action_check CHECK (action IN ('.$this->quotedSqlList($this->without(CoreAuditAction::values(), [
            'CONTRACT_EXPIRED',
            'APPLICATION_ACCESS_EXPIRED',
        ])).'))');
    }

    /**
     * @param  list<string>  $values
     */
    private function quotedSqlList(array $values): string
    {
        return implode(', ', array_map(fn (string $value): string => DB::getPdo()->quote($value), $values));
    }

    /**
     * @param  list<string>  $values
     * @param  list<string>  $removed
     * @return list<string>
     */
    private function without(array $values, array $removed): array
    {
        return array_values(array_filter($values, fn (string $value): bool => ! in_array($value, $removed, true)));
    }
};
