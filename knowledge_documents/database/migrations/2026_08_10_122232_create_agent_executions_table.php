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
        Schema::create('agent_executions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('request_id')->index();
            $table->string('profile')->index();
            $table->string('status')->index();
            $table->string('failure_category')->nullable()->index();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->unsignedSmallInteger('step_count')->default(0);
            $table->unsignedSmallInteger('tool_call_count')->default(0);
            $table->string('provider')->nullable()->index();
            $table->string('model')->nullable()->index();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->json('input_metadata')->nullable();
            $table->json('retrieval_metrics')->nullable();
            $table->json('answer_metrics')->nullable();
            $table->json('error_information')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['profile', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_executions');
    }
};
