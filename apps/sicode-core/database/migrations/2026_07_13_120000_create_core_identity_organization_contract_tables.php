<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pgcrypto');

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('display_name');
            $table->string('primary_email')->nullable();
            $table->string('primary_email_normalized')->nullable();
            $table->string('status');
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('active', 'blocked', 'disabled'))");
        DB::statement('CREATE INDEX users_primary_email_normalized_idx ON users (primary_email_normalized) WHERE primary_email_normalized IS NOT NULL');
        DB::statement('CREATE INDEX users_status_idx ON users (status)');

        Schema::create('external_identities', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id');
            $table->string('provider');
            $table->string('provider_context');
            $table->string('external_subject');
            $table->string('status');
            $table->timestampTz('linked_at');
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE external_identities ADD CONSTRAINT external_identities_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE RESTRICT ON DELETE RESTRICT');
        DB::statement("ALTER TABLE external_identities ADD CONSTRAINT external_identities_status_check CHECK (status IN ('active', 'revoked', 'archived'))");
        DB::statement('ALTER TABLE external_identities ADD CONSTRAINT external_identities_provider_subject_unique UNIQUE (provider, provider_context, external_subject)');
        DB::statement('CREATE INDEX external_identities_user_provider_context_idx ON external_identities (user_id, provider, provider_context)');

        Schema::create('organizations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('document_type')->nullable();
            $table->string('document_value')->nullable();
            $table->string('status');
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE organizations ADD CONSTRAINT organizations_status_check CHECK (status IN ('active', 'suspended', 'disabled'))");
        DB::statement('CREATE UNIQUE INDEX organizations_document_unique_idx ON organizations (document_type, document_value) WHERE document_type IS NOT NULL AND document_value IS NOT NULL');

        Schema::create('organization_memberships', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id');
            $table->foreignUuid('organization_id');
            $table->string('status');
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE organization_memberships ADD CONSTRAINT organization_memberships_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE RESTRICT ON DELETE RESTRICT');
        DB::statement('ALTER TABLE organization_memberships ADD CONSTRAINT organization_memberships_organization_id_foreign FOREIGN KEY (organization_id) REFERENCES organizations (id) ON UPDATE RESTRICT ON DELETE RESTRICT');
        DB::statement("ALTER TABLE organization_memberships ADD CONSTRAINT organization_memberships_status_check CHECK (status IN ('active', 'suspended', 'ended'))");
        DB::statement('ALTER TABLE organization_memberships ADD CONSTRAINT organization_memberships_period_check CHECK (ended_at IS NULL OR ended_at >= started_at)');
        DB::statement("ALTER TABLE organization_memberships ADD CONSTRAINT organization_memberships_status_period_check CHECK ((status = 'active' AND ended_at IS NULL) OR (status = 'suspended' AND ended_at IS NULL) OR (status = 'ended' AND ended_at IS NOT NULL))");
        DB::statement("CREATE UNIQUE INDEX organization_memberships_active_pair_unique_idx ON organization_memberships (user_id, organization_id) WHERE status = 'active'");
        DB::statement('CREATE INDEX organization_memberships_user_status_idx ON organization_memberships (user_id, status)');
        DB::statement('CREATE INDEX organization_memberships_organization_status_idx ON organization_memberships (organization_id, status)');

        Schema::create('contracts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('organization_id');
            $table->string('identifier')->nullable();
            $table->string('status');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE contracts ADD CONSTRAINT contracts_organization_id_foreign FOREIGN KEY (organization_id) REFERENCES organizations (id) ON UPDATE RESTRICT ON DELETE RESTRICT');
        DB::statement("ALTER TABLE contracts ADD CONSTRAINT contracts_status_check CHECK (status IN ('draft', 'active', 'suspended', 'ended'))");
        DB::statement('ALTER TABLE contracts ADD CONSTRAINT contracts_period_check CHECK (ends_at IS NULL OR ends_at >= starts_at)');
        DB::statement('CREATE INDEX contracts_organization_status_idx ON contracts (organization_id, status)');
        DB::statement('CREATE INDEX contracts_organization_period_idx ON contracts (organization_id, starts_at, ends_at)');
        DB::statement('CREATE INDEX contracts_identifier_idx ON contracts (identifier) WHERE identifier IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('organization_memberships');
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('external_identities');
        Schema::dropIfExists('users');
    }
};
