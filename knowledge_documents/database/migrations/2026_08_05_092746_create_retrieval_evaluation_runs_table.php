<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retrieval_evaluation_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('evaluation_question_id')->constrained('evaluation_questions')->cascadeOnDelete();
            $table->text('query');
            $table->unsignedInteger('top_k');
            $table->decimal('minimum_score', 5, 4)->nullable();
            $table->jsonb('retrieved_results');
            $table->jsonb('expected_references');
            $table->boolean('hit');
            $table->decimal('precision', 8, 6);
            $table->decimal('recall', 8, 6);
            $table->decimal('reciprocal_rank', 8, 6);
            $table->unsignedInteger('execution_time_ms');
            $table->timestampTz('created_at')->nullable();

            $table->index(['evaluation_question_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retrieval_evaluation_runs');
    }
};
