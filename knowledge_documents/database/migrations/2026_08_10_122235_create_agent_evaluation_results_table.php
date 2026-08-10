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
        Schema::create('agent_evaluation_results', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('agent_evaluation_run_id')->constrained('agent_evaluation_runs')->cascadeOnDelete();
            $table->string('scenario_name')->index();
            $table->string('status')->index();
            $table->unsignedSmallInteger('step_count')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->json('expected_tools')->nullable();
            $table->json('actual_tools')->nullable();
            $table->json('missing_tools')->nullable();
            $table->json('extra_tools')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_evaluation_results');
    }
};
