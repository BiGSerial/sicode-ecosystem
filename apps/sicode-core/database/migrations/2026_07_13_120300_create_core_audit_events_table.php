<?php

use App\CoreAudit\CoreAuditAction;
use App\CoreAudit\CoreAuditActorType;
use App\CoreAudit\CoreAuditSubjectType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_audit_events', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->timestampTz('occurred_at');
            $table->string('actor_type', 40);
            $table->uuid('actor_id')->nullable();
            $table->string('action', 80);
            $table->string('subject_type', 80);
            $table->uuid('subject_id');
            $table->foreignUuid('application_id')->nullable();
            $table->foreignUuid('context_id')->nullable();
            $table->string('reason', 500)->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->jsonb('details')->nullable();
        });

        DB::statement('ALTER TABLE core_audit_events ADD CONSTRAINT core_audit_events_application_id_foreign FOREIGN KEY (application_id) REFERENCES applications (id) ON UPDATE RESTRICT ON DELETE RESTRICT');
        DB::statement('ALTER TABLE core_audit_events ADD CONSTRAINT core_audit_events_context_id_foreign FOREIGN KEY (context_id) REFERENCES application_contexts (id) ON UPDATE RESTRICT ON DELETE RESTRICT');
        DB::statement('ALTER TABLE core_audit_events ADD CONSTRAINT core_audit_events_actor_type_check CHECK (actor_type IN ('.$this->quotedSqlList(CoreAuditActorType::values()).'))');
        DB::statement('ALTER TABLE core_audit_events ADD CONSTRAINT core_audit_events_action_check CHECK (action IN ('.$this->quotedSqlList(CoreAuditAction::values()).'))');
        DB::statement('ALTER TABLE core_audit_events ADD CONSTRAINT core_audit_events_subject_type_check CHECK (subject_type IN ('.$this->quotedSqlList(CoreAuditSubjectType::values()).'))');
        DB::statement("ALTER TABLE core_audit_events ADD CONSTRAINT core_audit_events_actor_id_check CHECK ((actor_type = 'SYSTEM' AND actor_id IS NULL) OR (actor_type <> 'SYSTEM' AND actor_id IS NOT NULL))");
        DB::statement('ALTER TABLE core_audit_events ADD CONSTRAINT core_audit_events_context_requires_application_check CHECK (context_id IS NULL OR application_id IS NOT NULL)');
        DB::statement("ALTER TABLE core_audit_events ADD CONSTRAINT core_audit_events_details_object_check CHECK (details IS NULL OR jsonb_typeof(details) = 'object')");

        DB::statement('CREATE INDEX core_audit_events_occurred_at_idx ON core_audit_events (occurred_at)');
        DB::statement('CREATE INDEX core_audit_events_actor_idx ON core_audit_events (actor_type, actor_id)');
        DB::statement('CREATE INDEX core_audit_events_subject_idx ON core_audit_events (subject_type, subject_id)');
        DB::statement('CREATE INDEX core_audit_events_action_idx ON core_audit_events (action)');
        DB::statement('CREATE INDEX core_audit_events_correlation_id_idx ON core_audit_events (correlation_id) WHERE correlation_id IS NOT NULL');
        DB::statement('CREATE INDEX core_audit_events_application_context_idx ON core_audit_events (application_id, context_id) WHERE application_id IS NOT NULL');

        DB::unprepared(<<<'SQL'
CREATE TRIGGER core_audit_events_context_application_check
BEFORE INSERT OR UPDATE OF application_id, context_id
ON core_audit_events
FOR EACH ROW
EXECUTE FUNCTION core_assert_context_matches_application();
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION core_prevent_audit_event_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION 'core_audit_events are append-only'
        USING ERRCODE = '23514';
END;
$$;
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER core_audit_events_prevent_update
BEFORE UPDATE
ON core_audit_events
FOR EACH ROW
EXECUTE FUNCTION core_prevent_audit_event_mutation();
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER core_audit_events_prevent_delete
BEFORE DELETE
ON core_audit_events
FOR EACH ROW
EXECUTE FUNCTION core_prevent_audit_event_mutation();
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS core_audit_events_prevent_delete ON core_audit_events');
        DB::statement('DROP TRIGGER IF EXISTS core_audit_events_prevent_update ON core_audit_events');
        DB::statement('DROP TRIGGER IF EXISTS core_audit_events_context_application_check ON core_audit_events');

        Schema::dropIfExists('core_audit_events');

        DB::statement('DROP FUNCTION IF EXISTS core_prevent_audit_event_mutation()');
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
