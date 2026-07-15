<?php

use App\CoreAudit\CoreAuditAction;
use App\CoreAudit\CoreAuditSubjectType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE core_audit_events DROP CONSTRAINT core_audit_events_action_check');
        DB::statement('ALTER TABLE core_audit_events DROP CONSTRAINT core_audit_events_subject_type_check');

        DB::statement('ALTER TABLE core_audit_events ADD CONSTRAINT core_audit_events_action_check CHECK (action IN ('.$this->quotedSqlList(CoreAuditAction::values()).'))');
        DB::statement('ALTER TABLE core_audit_events ADD CONSTRAINT core_audit_events_subject_type_check CHECK (subject_type IN ('.$this->quotedSqlList(CoreAuditSubjectType::values()).'))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE core_audit_events DROP CONSTRAINT core_audit_events_action_check');
        DB::statement('ALTER TABLE core_audit_events DROP CONSTRAINT core_audit_events_subject_type_check');

        DB::statement('ALTER TABLE core_audit_events ADD CONSTRAINT core_audit_events_action_check CHECK (action IN ('.$this->quotedSqlList([
            'USER_BLOCKED',
            'USER_UNBLOCKED',
            'USER_DEACTIVATED',
            'USER_CANONICAL_NAME_CHANGED',
            'USER_CANONICAL_EMAIL_CHANGED',
            'LOCAL_PASSWORD_CREDENTIAL_CREATED',
            'LOCAL_PASSWORD_CREDENTIAL_CHANGED',
            'LOCAL_PASSWORD_CREDENTIAL_DISABLED',
            'LOCAL_PASSWORD_CREDENTIAL_REHASHED',
            'EXTERNAL_IDENTITY_LINKED',
            'EXTERNAL_IDENTITY_REVOKED',
            'EXTERNAL_IDENTITY_ARCHIVED',
            'EXTERNAL_IDENTITY_RECONCILED',
            'ORGANIZATION_MEMBERSHIP_CREATED',
            'ORGANIZATION_MEMBERSHIP_ACTIVATED',
            'ORGANIZATION_MEMBERSHIP_SUSPENDED',
            'ORGANIZATION_MEMBERSHIP_REACTIVATED',
            'ORGANIZATION_MEMBERSHIP_ENDED',
            'CONTRACT_CREATED',
            'CONTRACT_ACTIVATED',
            'CONTRACT_SUSPENDED',
            'CONTRACT_REACTIVATED',
            'CONTRACT_ENDED',
            'APPLICATION_CREATED',
            'APPLICATION_DEACTIVATED',
            'APPLICATION_CLIENT_CREATED',
            'APPLICATION_CLIENT_DEACTIVATED',
            'APPLICATION_CONTEXT_CREATED',
            'APPLICATION_CONTEXT_DEACTIVATED',
            'APPLICATION_ENTRY_REQUIREMENTS_CHANGED',
            'APPLICATION_ACCESS_GRANTED',
            'APPLICATION_ACCESS_REVOKED',
            'APPLICATION_ACCESS_SUSPENDED',
            'APPLICATION_ACCESS_REACTIVATED',
            'CONTRACT_APPLICATION_GRANT_GRANTED',
            'CONTRACT_APPLICATION_GRANT_REVOKED',
            'CONTRACT_APPLICATION_GRANT_SUSPENDED',
            'CONTRACT_APPLICATION_GRANT_REACTIVATED',
        ]).'))');
        DB::statement('ALTER TABLE core_audit_events ADD CONSTRAINT core_audit_events_subject_type_check CHECK (subject_type IN ('.$this->quotedSqlList([
            'USER',
            'LOCAL_PASSWORD_CREDENTIAL',
            'EXTERNAL_IDENTITY',
            'ORGANIZATION',
            'ORGANIZATION_MEMBERSHIP',
            'CONTRACT',
            'APPLICATION',
            'APPLICATION_CLIENT',
            'APPLICATION_CONTEXT',
            'APPLICATION_ACCESS',
            'CONTRACT_APPLICATION_GRANT',
        ]).'))');
    }

    /**
     * @param  list<string>  $values
     */
    private function quotedSqlList(array $values): string
    {
        return implode(
            ', ',
            array_map(fn (string $value): string => DB::getPdo()->quote($value), $values),
        );
    }
};
