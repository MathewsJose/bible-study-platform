<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agent_replays', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('original_execution_id')->constrained('agent_executions')->cascadeOnDelete();
            $table->foreignUuid('replay_execution_id')->nullable()->constrained('agent_executions')->nullOnDelete();
            $table->string('mode')->default('live')->index();
            $table->string('status')->default('running')->index();
            $table->string('comparison_status')->nullable()->index();
            $table->boolean('strict')->default(false)->index();
            $table->boolean('dry_run')->default(false);
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->json('original_fingerprint')->nullable();
            $table->json('replay_fingerprint')->nullable();
            $table->json('corpus_snapshot')->nullable();
            $table->json('configuration_snapshot')->nullable();
            $table->json('comparison')->nullable();
            $table->json('divergence_summary')->nullable();
            $table->json('error_information')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['original_execution_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_replays');
    }
};
