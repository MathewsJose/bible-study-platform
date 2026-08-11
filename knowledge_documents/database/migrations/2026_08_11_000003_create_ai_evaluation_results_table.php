<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_evaluation_results', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('ai_evaluation_run_id')->constrained('ai_evaluation_runs')->cascadeOnDelete();
            $table->foreignUuid('evaluation_question_id')->nullable()->constrained('evaluation_questions')->nullOnDelete();
            $table->string('evaluation_type');
            $table->string('category')->nullable();
            $table->string('difficulty')->nullable();
            $table->string('status');
            $table->float('score')->default(0.0);
            $table->json('metrics')->nullable();
            $table->json('expected')->nullable();
            $table->json('actual')->nullable();
            $table->json('warnings')->nullable();
            $table->unsignedInteger('latency_ms')->default(0);
            $table->timestamps();

            $table->index(['evaluation_type', 'status']);
            $table->index(['category', 'difficulty']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_evaluation_results');
    }
};
