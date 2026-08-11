<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_evaluation_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('evaluation_type');
            $table->string('status')->default('running');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('total_questions')->default(0);
            $table->json('metrics')->nullable();
            $table->json('configuration')->nullable();
            $table->json('fingerprints')->nullable();
            $table->json('thresholds')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['evaluation_type', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_evaluation_runs');
    }
};
