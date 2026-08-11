<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_questions', function (Blueprint $table): void {
            $table->string('difficulty')->nullable()->after('category');
            $table->json('expected_answer_facts')->nullable()->after('expected_source_types');
            $table->json('required_citations')->nullable()->after('expected_answer_facts');
            $table->json('metadata')->nullable()->after('notes');
        });

        Schema::table('retrieval_evaluation_runs', function (Blueprint $table): void {
            $table->float('ndcg')->default(0.0)->after('reciprocal_rank');
            $table->json('source_coverage')->nullable()->after('expected_references');
        });

        Schema::table('retrieval_evaluation_summaries', function (Blueprint $table): void {
            $table->float('mean_ndcg')->default(0.0)->after('mrr');
            $table->float('mean_source_coverage')->default(0.0)->after('mean_ndcg');
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_questions', function (Blueprint $table): void {
            $table->dropColumn(['difficulty', 'expected_answer_facts', 'required_citations', 'metadata']);
        });

        Schema::table('retrieval_evaluation_runs', function (Blueprint $table): void {
            $table->dropColumn(['ndcg', 'source_coverage']);
        });

        Schema::table('retrieval_evaluation_summaries', function (Blueprint $table): void {
            $table->dropColumn(['mean_ndcg', 'mean_source_coverage']);
        });
    }
};
