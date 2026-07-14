<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_accesses', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id');
            $table->foreignUuid('application_id');
            $table->foreignUuid('context_id')->nullable();
            $table->string('status');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE application_accesses ADD CONSTRAINT application_accesses_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE RESTRICT ON DELETE RESTRICT');
        DB::statement('ALTER TABLE application_accesses ADD CONSTRAINT application_accesses_application_id_foreign FOREIGN KEY (application_id) REFERENCES applications (id) ON UPDATE RESTRICT ON DELETE RESTRICT');
        DB::statement('ALTER TABLE application_accesses ADD CONSTRAINT application_accesses_context_id_foreign FOREIGN KEY (context_id) REFERENCES application_contexts (id) ON UPDATE RESTRICT ON DELETE RESTRICT');
        DB::statement("ALTER TABLE application_accesses ADD CONSTRAINT application_accesses_status_check CHECK (status IN ('active', 'suspended', 'revoked'))");
        DB::statement('ALTER TABLE application_accesses ADD CONSTRAINT application_accesses_period_check CHECK (ends_at IS NULL OR ends_at >= starts_at)');
        DB::statement('CREATE INDEX application_accesses_user_application_context_status_idx ON application_accesses (user_id, application_id, context_id, status)');
        DB::statement("CREATE UNIQUE INDEX application_accesses_active_equivalent_unique_idx ON application_accesses (user_id, application_id, COALESCE(context_id, '00000000-0000-0000-0000-000000000000'::uuid)) WHERE status = 'active'");

        Schema::create('contract_application_grants', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('contract_id');
            $table->foreignUuid('application_id');
            $table->foreignUuid('context_id')->nullable();
            $table->string('status');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE contract_application_grants ADD CONSTRAINT contract_application_grants_contract_id_foreign FOREIGN KEY (contract_id) REFERENCES contracts (id) ON UPDATE RESTRICT ON DELETE RESTRICT');
        DB::statement('ALTER TABLE contract_application_grants ADD CONSTRAINT contract_application_grants_application_id_foreign FOREIGN KEY (application_id) REFERENCES applications (id) ON UPDATE RESTRICT ON DELETE RESTRICT');
        DB::statement('ALTER TABLE contract_application_grants ADD CONSTRAINT contract_application_grants_context_id_foreign FOREIGN KEY (context_id) REFERENCES application_contexts (id) ON UPDATE RESTRICT ON DELETE RESTRICT');
        DB::statement("ALTER TABLE contract_application_grants ADD CONSTRAINT contract_application_grants_status_check CHECK (status IN ('active', 'suspended', 'revoked'))");
        DB::statement('ALTER TABLE contract_application_grants ADD CONSTRAINT contract_application_grants_period_check CHECK (ends_at IS NULL OR ends_at >= starts_at)');
        DB::statement('CREATE INDEX contract_application_grants_contract_application_context_status_idx ON contract_application_grants (contract_id, application_id, context_id, status)');
        DB::statement("CREATE UNIQUE INDEX contract_application_grants_active_equivalent_unique_idx ON contract_application_grants (contract_id, application_id, COALESCE(context_id, '00000000-0000-0000-0000-000000000000'::uuid)) WHERE status = 'active'");

        DB::unprepared(<<<'SQL'
CREATE TRIGGER application_accesses_context_application_check
BEFORE INSERT OR UPDATE OF application_id, context_id
ON application_accesses
FOR EACH ROW
EXECUTE FUNCTION core_assert_context_matches_application();
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER contract_application_grants_context_application_check
BEFORE INSERT OR UPDATE OF application_id, context_id
ON contract_application_grants
FOR EACH ROW
EXECUTE FUNCTION core_assert_context_matches_application();
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS contract_application_grants_context_application_check ON contract_application_grants');
        DB::statement('DROP TRIGGER IF EXISTS application_accesses_context_application_check ON application_accesses');

        Schema::dropIfExists('contract_application_grants');
        Schema::dropIfExists('application_accesses');
    }
};
