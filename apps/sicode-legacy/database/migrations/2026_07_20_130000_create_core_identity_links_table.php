<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('core_identity_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('core_issuer', 120);
            $table->uuid('core_subject');
            $table->uuid('legacy_user_id');
            $table->string('application_context', 40);
            $table->string('status', 20)->default('active');
            $table->timestamp('linked_at')->useCurrent();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->foreign('legacy_user_id')->references('id')->on('users')->restrictOnUpdate()->restrictOnDelete();
            $table->unique(['core_issuer', 'core_subject', 'application_context', 'status'], 'core_identity_links_subject_context_status_unique');
            $table->unique(['legacy_user_id', 'application_context', 'status'], 'core_identity_links_user_context_status_unique');
            $table->index(['core_issuer', 'core_subject', 'application_context'], 'core_identity_links_subject_context_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_identity_links');
    }
};
