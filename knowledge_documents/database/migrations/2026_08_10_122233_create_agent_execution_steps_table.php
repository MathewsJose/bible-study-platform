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
        Schema::create('agent_execution_steps', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('agent_execution_id')->constrained('agent_executions')->cascadeOnDelete();
            $table->unsignedSmallInteger('step_number')->index();
            $table->string('action_type')->default('tool')->index();
            $table->string('tool_name')->index();
            $table->string('status')->index();
            $table->string('failure_category')->nullable()->index();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->json('input_metadata')->nullable();
            $table->json('output_metadata')->nullable();
            $table->json('validation_errors')->nullable();
            $table->json('error_information')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['agent_execution_id', 'step_number', 'tool_name']);
            $table->index(['tool_name', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_execution_steps');
    }
};
