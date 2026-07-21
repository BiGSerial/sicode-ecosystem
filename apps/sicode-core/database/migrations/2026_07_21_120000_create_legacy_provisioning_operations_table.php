<?php

use App\CoreAudit\CoreAuditAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_provisioning_operations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('target_application');
            $table->string('target_context');
            $table->string('entity_type');
            $table->uuid('entity_id');
            $table->uuid('organization_id')->nullable();
            $table->string('idempotency_key_hash', 64);
            $table->timestampTz('requested_at');
            $table->timestampTz('completed_at')->nullable();
            $table->string('outcome')->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->string('last_error_category')->nullable();
            $table->string('remote_local_id')->nullable();
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE legacy_provisioning_operations ADD CONSTRAINT legacy_provisioning_operations_entity_type_check CHECK (entity_type IN ('organization', 'user'))");
        DB::statement("ALTER TABLE legacy_provisioning_operations ADD CONSTRAINT legacy_provisioning_operations_outcome_check CHECK (outcome IS NULL OR outcome IN ('created', 'already_provisioned', 'updated', 'conflict', 'rejected', 'unavailable'))");
        DB::statement('ALTER TABLE legacy_provisioning_operations ADD CONSTRAINT legacy_provisioning_operations_attempt_count_check CHECK (attempt_count >= 0)');
        DB::statement('ALTER TABLE legacy_provisioning_operations ADD CONSTRAINT legacy_provisioning_operations_idempotency_key_hash_unique UNIQUE (idempotency_key_hash)');
        DB::statement('CREATE INDEX legacy_provisioning_operations_entity_idx ON legacy_provisioning_operations (target_application, target_context, entity_type, entity_id)');
        DB::statement('CREATE INDEX legacy_provisioning_operations_organization_idx ON legacy_provisioning_operations (organization_id) WHERE organization_id IS NOT NULL');
        DB::statement('CREATE INDEX legacy_provisioning_operations_outcome_idx ON legacy_provisioning_operations (outcome) WHERE outcome IS NOT NULL');

        DB::statement('ALTER TABLE core_audit_events DROP CONSTRAINT core_audit_events_action_check');
        DB::statement('ALTER TABLE core_audit_events ADD CONSTRAINT core_audit_events_action_check CHECK (action IN ('.$this->quotedSqlList(CoreAuditAction::values()).'))');
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_provisioning_operations');

        DB::statement('ALTER TABLE core_audit_events DROP CONSTRAINT IF EXISTS core_audit_events_action_check');
        DB::statement('ALTER TABLE core_audit_events ADD CONSTRAINT core_audit_events_action_check CHECK (action IN ('.$this->quotedSqlList($this->without(CoreAuditAction::values(), [
            'LEGACY_PROVISIONING_REQUESTED',
            'LEGACY_ORGANIZATION_PROVISIONED',
            'LEGACY_ORGANIZATION_ALREADY_PROVISIONED',
            'LEGACY_USER_PROVISIONED',
            'LEGACY_USER_ALREADY_PROVISIONED',
            'LEGACY_PROVISIONING_CONFLICT',
            'LEGACY_PROVISIONING_REJECTED',
            'LEGACY_PROVISIONING_UNAVAILABLE',
            'LEGACY_PROVISIONING_PARTIALLY_COMPLETED',
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
