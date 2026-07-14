<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('code');
            $table->string('name');
            $table->string('status');
            $table->boolean('requires_organization')->default(false);
            $table->boolean('requires_contract')->default(false);
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE applications ADD CONSTRAINT applications_status_check CHECK (status IN ('active', 'disabled'))");
        DB::statement("ALTER TABLE applications ADD CONSTRAINT applications_code_format_check CHECK (code ~ '^[a-z0-9][a-z0-9-]*$')");
        DB::statement('ALTER TABLE applications ADD CONSTRAINT applications_code_unique UNIQUE (code)');

        Schema::create('application_contexts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('application_id');
            $table->string('code');
            $table->string('name');
            $table->string('status');
            $table->boolean('requires_organization')->nullable();
            $table->boolean('requires_contract')->nullable();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE application_contexts ADD CONSTRAINT application_contexts_application_id_foreign FOREIGN KEY (application_id) REFERENCES applications (id) ON UPDATE RESTRICT ON DELETE RESTRICT');
        DB::statement("ALTER TABLE application_contexts ADD CONSTRAINT application_contexts_status_check CHECK (status IN ('active', 'disabled'))");
        DB::statement("ALTER TABLE application_contexts ADD CONSTRAINT application_contexts_code_format_check CHECK (code ~ '^[a-z0-9][a-z0-9-]*$')");
        DB::statement('ALTER TABLE application_contexts ADD CONSTRAINT application_contexts_application_code_unique UNIQUE (application_id, code)');

        Schema::create('application_clients', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('application_id');
            $table->foreignUuid('context_id')->nullable();
            $table->string('client_identifier');
            $table->string('name');
            $table->string('type');
            $table->string('status');
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE application_clients ADD COLUMN redirect_uris text[] NULL');
        DB::statement('ALTER TABLE application_clients ADD CONSTRAINT application_clients_application_id_foreign FOREIGN KEY (application_id) REFERENCES applications (id) ON UPDATE RESTRICT ON DELETE RESTRICT');
        DB::statement('ALTER TABLE application_clients ADD CONSTRAINT application_clients_context_id_foreign FOREIGN KEY (context_id) REFERENCES application_contexts (id) ON UPDATE RESTRICT ON DELETE RESTRICT');
        DB::statement("ALTER TABLE application_clients ADD CONSTRAINT application_clients_type_format_check CHECK (type ~ '^[a-z][a-z0-9_-]*$')");
        DB::statement("ALTER TABLE application_clients ADD CONSTRAINT application_clients_status_check CHECK (status IN ('active', 'disabled'))");
        DB::statement('ALTER TABLE application_clients ADD CONSTRAINT application_clients_client_identifier_unique UNIQUE (client_identifier)');
        DB::statement('CREATE INDEX application_clients_application_context_status_idx ON application_clients (application_id, context_id, status)');

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION core_assert_context_matches_application()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    actual_application_id uuid;
BEGIN
    IF NEW.context_id IS NULL THEN
        RETURN NEW;
    END IF;

    SELECT application_id
      INTO actual_application_id
      FROM application_contexts
     WHERE id = NEW.context_id;

    IF actual_application_id IS NULL OR actual_application_id <> NEW.application_id THEN
        RAISE EXCEPTION 'context_id must belong to the same application_id'
            USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$;
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER application_clients_context_application_check
BEFORE INSERT OR UPDATE OF application_id, context_id
ON application_clients
FOR EACH ROW
EXECUTE FUNCTION core_assert_context_matches_application();
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS application_clients_context_application_check ON application_clients');

        Schema::dropIfExists('application_clients');
        Schema::dropIfExists('application_contexts');
        Schema::dropIfExists('applications');

        DB::statement('DROP FUNCTION IF EXISTS core_assert_context_matches_application()');
    }
};
