<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_launches', function (Blueprint $table) {
            $table->foreignUuid('authorized_organization_id')->nullable()->after('context_id');
        });

        DB::statement('ALTER TABLE application_launches ADD CONSTRAINT application_launches_authorized_organization_id_foreign FOREIGN KEY (authorized_organization_id) REFERENCES organizations (id) ON UPDATE RESTRICT ON DELETE RESTRICT');
        DB::statement('CREATE INDEX application_launches_authorized_organization_idx ON application_launches (authorized_organization_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS application_launches_authorized_organization_idx');
        DB::statement('ALTER TABLE application_launches DROP CONSTRAINT IF EXISTS application_launches_authorized_organization_id_foreign');

        Schema::table('application_launches', function (Blueprint $table) {
            $table->dropColumn('authorized_organization_id');
        });
    }
};
