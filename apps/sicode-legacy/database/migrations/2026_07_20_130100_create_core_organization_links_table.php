<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('core_organization_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('core_issuer', 120);
            $table->uuid('core_organization_id');
            $table->string('application_context', 40);
            $table->uuid('company_id');
            $table->string('status', 20)->default('active');
            $table->timestamp('linked_at')->useCurrent();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->restrictOnUpdate()->restrictOnDelete();
            $table->unique(['core_issuer', 'core_organization_id', 'application_context', 'status'], 'core_org_links_org_context_status_unique');
            $table->unique(['company_id', 'application_context', 'status'], 'core_org_links_company_context_status_unique');
            $table->index(['core_issuer', 'core_organization_id', 'application_context'], 'core_org_links_org_context_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_organization_links');
    }
};
