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
        Schema::create('agent_evaluation_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->index();
            $table->string('dataset_version')->index();
            $table->string('profile')->index();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedSmallInteger('total_tasks')->default(0);
            $table->unsignedSmallInteger('successful_tasks')->default(0);
            $table->unsignedSmallInteger('failed_tasks')->default(0);
            $table->decimal('success_rate', 6, 4)->default(0);
            $table->decimal('average_steps', 8, 4)->default(0);
            $table->decimal('average_latency_ms', 10, 2)->default(0);
            $table->unsignedSmallInteger('unnecessary_tool_calls')->default(0);
            $table->json('regression')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_evaluation_runs');
    }
};
