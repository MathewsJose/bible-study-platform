<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retrieval_evaluation_summaries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedInteger('total_questions');
            $table->decimal('hit_rate', 8, 6);
            $table->decimal('mean_precision', 8, 6);
            $table->decimal('mean_recall', 8, 6);
            $table->decimal('mrr', 8, 6);
            $table->unsignedInteger('average_latency_ms');
            $table->jsonb('configuration');
            $table->timestampTz('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retrieval_evaluation_summaries');
    }
};
