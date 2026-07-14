<?php

use App\CoreAudit\CoreAuditAction;
use App\CoreAudit\CoreAuditSubjectType;
use App\Models\LocalPasswordCredentialStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $previousAuditActions = [
        'USER_BLOCKED',
        'USER_UNBLOCKED',
        'USER_DEACTIVATED',
        'USER_CANONICAL_NAME_CHANGED',
        'USER_CANONICAL_EMAIL_CHANGED',
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
    ];

    /**
     * @var list<string>
     */
    private array $previousAuditSubjects = [
        'USER',
        'EXTERNAL_IDENTITY',
        'ORGANIZATION',
        'ORGANIZATION_MEMBERSHIP',
        'CONTRACT',
        'APPLICATION',
        'APPLICATION_CLIENT',
        'APPLICATION_CONTEXT',
        'APPLICATION_ACCESS',
        'CONTRACT_APPLICATION_GRANT',
    ];

    public function up(): void
    {
        $this->replaceAuditCatalogConstraints(
            CoreAuditAction::values(),
            CoreAuditSubjectType::values(),
        );

        Schema::create('local_password_credentials', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id');
            $table->string('password_hash', 255);
            $table->string('status', 20);
            $table->timestampTz('password_changed_at');
            $table->timestampTz('invalidated_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE local_password_credentials ADD CONSTRAINT local_password_credentials_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE RESTRICT ON DELETE RESTRICT');
        DB::statement('ALTER TABLE local_password_credentials ADD CONSTRAINT local_password_credentials_user_id_unique UNIQUE (user_id)');
        DB::statement('ALTER TABLE local_password_credentials ADD CONSTRAINT local_password_credentials_status_check CHECK (status IN ('.$this->quotedSqlList(LocalPasswordCredentialStatus::values()).'))');
        DB::statement("ALTER TABLE local_password_credentials ADD CONSTRAINT local_password_credentials_status_invalidated_check CHECK ((status = 'active' AND invalidated_at IS NULL) OR (status = 'disabled' AND invalidated_at IS NOT NULL))");
        DB::statement('ALTER TABLE local_password_credentials ADD CONSTRAINT local_password_credentials_password_hash_not_blank_check CHECK (length(password_hash) > 0)');
        DB::statement('CREATE INDEX local_password_credentials_status_idx ON local_password_credentials (status)');
    }

    public function down(): void
    {
        Schema::dropIfExists('local_password_credentials');

        $this->replaceAuditCatalogConstraints(
            $this->previousAuditActions,
            $this->previousAuditSubjects,
        );
    }

    /**
     * @param  list<string>  $actions
     * @param  list<string>  $subjects
     */
    private function replaceAuditCatalogConstraints(array $actions, array $subjects): void
    {
        DB::statement('ALTER TABLE core_audit_events DROP CONSTRAINT IF EXISTS core_audit_events_action_check');
        DB::statement('ALTER TABLE core_audit_events DROP CONSTRAINT IF EXISTS core_audit_events_subject_type_check');
        DB::statement('ALTER TABLE core_audit_events ADD CONSTRAINT core_audit_events_action_check CHECK (action IN ('.$this->quotedSqlList($actions).'))');
        DB::statement('ALTER TABLE core_audit_events ADD CONSTRAINT core_audit_events_subject_type_check CHECK (subject_type IN ('.$this->quotedSqlList($subjects).'))');
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
