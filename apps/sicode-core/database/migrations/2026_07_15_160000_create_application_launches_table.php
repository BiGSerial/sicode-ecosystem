<?php

use App\CoreAudit\CoreAuditAction;
use App\CoreAudit\CoreAuditSubjectType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_launches', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id');
            $table->foreignUuid('application_id');
            $table->foreignUuid('context_id')->nullable();
            $table->foreignUuid('client_id');
            $table->string('token_hash', 64);
            $table->string('state_hash', 64);
            $table->text('callback_url');
            $table->timestampTz('issued_at');
            $table->timestampTz('expires_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->foreignUuid('consumed_by_client_id')->nullable();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE application_launches ADD CONSTRAINT application_launches_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE RESTRICT ON DELETE RESTRICT');
        DB::statement('ALTER TABLE application_launches ADD CONSTRAINT application_launches_application_id_foreign FOREIGN KEY (application_id) REFERENCES applications (id) ON UPDATE RESTRICT ON DELETE RESTRICT');
        DB::statement('ALTER TABLE application_launches ADD CONSTRAINT application_launches_context_id_foreign FOREIGN KEY (context_id) REFERENCES application_contexts (id) ON UPDATE RESTRICT ON DELETE RESTRICT');
        DB::statement('ALTER TABLE application_launches ADD CONSTRAINT application_launches_client_id_foreign FOREIGN KEY (client_id) REFERENCES application_clients (id) ON UPDATE RESTRICT ON DELETE RESTRICT');
        DB::statement('ALTER TABLE application_launches ADD CONSTRAINT application_launches_consumed_by_client_id_foreign FOREIGN KEY (consumed_by_client_id) REFERENCES application_clients (id) ON UPDATE RESTRICT ON DELETE RESTRICT');
        DB::statement("ALTER TABLE application_launches ADD CONSTRAINT application_launches_token_hash_format_check CHECK (token_hash ~ '^[a-f0-9]{64}$')");
        DB::statement("ALTER TABLE application_launches ADD CONSTRAINT application_launches_state_hash_format_check CHECK (state_hash ~ '^[a-f0-9]{64}$')");
        DB::statement("ALTER TABLE application_launches ADD CONSTRAINT application_launches_callback_https_check CHECK (callback_url ~ '^https://[^[:space:]]+$')");
        DB::statement('ALTER TABLE application_launches ADD CONSTRAINT application_launches_period_check CHECK (expires_at > issued_at)');
        DB::statement('ALTER TABLE application_launches ADD CONSTRAINT application_launches_consumed_at_check CHECK (consumed_at IS NULL OR consumed_at >= issued_at)');
        DB::statement('ALTER TABLE application_launches ADD CONSTRAINT application_launches_consumed_client_check CHECK ((consumed_at IS NULL AND consumed_by_client_id IS NULL) OR (consumed_at IS NOT NULL AND consumed_by_client_id = client_id))');
        DB::statement('ALTER TABLE application_launches ADD CONSTRAINT application_launches_token_hash_unique UNIQUE (token_hash)');
        DB::statement('CREATE INDEX application_launches_application_context_idx ON application_launches (application_id, context_id)');
        DB::statement('CREATE INDEX application_launches_client_expires_idx ON application_launches (client_id, expires_at)');

        DB::unprepared(<<<'SQL'
CREATE TRIGGER application_launches_context_application_check
BEFORE INSERT OR UPDATE OF application_id, context_id
ON application_launches
FOR EACH ROW
EXECUTE FUNCTION core_assert_context_matches_application();
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION core_assert_launch_client_matches_application()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    actual_application_id uuid;
    actual_context_id uuid;
BEGIN
    SELECT application_id, context_id
      INTO actual_application_id, actual_context_id
      FROM application_clients
     WHERE id = NEW.client_id;

    IF actual_application_id IS NULL OR actual_application_id <> NEW.application_id THEN
        RAISE EXCEPTION 'client_id must belong to the same application_id'
            USING ERRCODE = '23514';
    END IF;

    IF actual_context_id IS DISTINCT FROM NEW.context_id THEN
        RAISE EXCEPTION 'client_id must belong to the same context_id'
            USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$;
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER application_launches_client_application_check
BEFORE INSERT OR UPDATE OF application_id, context_id, client_id
ON application_launches
FOR EACH ROW
EXECUTE FUNCTION core_assert_launch_client_matches_application();
SQL);

        DB::statement('ALTER TABLE core_audit_events DROP CONSTRAINT core_audit_events_action_check');
        DB::statement('ALTER TABLE core_audit_events DROP CONSTRAINT core_audit_events_subject_type_check');
        DB::statement('ALTER TABLE core_audit_events ADD CONSTRAINT core_audit_events_action_check CHECK (action IN ('.$this->quotedSqlList(CoreAuditAction::values()).'))');
        DB::statement('ALTER TABLE core_audit_events ADD CONSTRAINT core_audit_events_subject_type_check CHECK (subject_type IN ('.$this->quotedSqlList(CoreAuditSubjectType::values()).'))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE core_audit_events DROP CONSTRAINT core_audit_events_action_check');
        DB::statement('ALTER TABLE core_audit_events DROP CONSTRAINT core_audit_events_subject_type_check');
        DB::statement('ALTER TABLE core_audit_events ADD CONSTRAINT core_audit_events_action_check CHECK (action IN ('.$this->quotedSqlList($this->without(CoreAuditAction::values(), [
            'APPLICATION_LAUNCH_ISSUED',
            'APPLICATION_LAUNCH_REJECTED',
            'APPLICATION_LAUNCH_EXCHANGED',
            'APPLICATION_LAUNCH_EXCHANGE_REJECTED',
            'APPLICATION_LAUNCH_REPLAY_REJECTED',
        ])).'))');
        DB::statement('ALTER TABLE core_audit_events ADD CONSTRAINT core_audit_events_subject_type_check CHECK (subject_type IN ('.$this->quotedSqlList($this->without(CoreAuditSubjectType::values(), [
            'APPLICATION_LAUNCH',
            'APPLICATION_LAUNCH_ATTEMPT',
        ])).'))');

        DB::statement('DROP TRIGGER IF EXISTS application_launches_client_application_check ON application_launches');
        DB::statement('DROP TRIGGER IF EXISTS application_launches_context_application_check ON application_launches');

        Schema::dropIfExists('application_launches');

        DB::statement('DROP FUNCTION IF EXISTS core_assert_launch_client_matches_application()');
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

    /**
     * @param  list<string>  $values
     * @param  list<string>  $excluded
     * @return list<string>
     */
    private function without(array $values, array $excluded): array
    {
        return array_values(array_filter(
            $values,
            fn (string $value): bool => ! in_array($value, $excluded, true),
        ));
    }
};
