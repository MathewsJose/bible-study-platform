<?php

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
        Schema::create('ai_answer_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('request_id', 120);
            $table->string('answer_execution_id', 120)->nullable();
            $table->string('rating', 32);
            $table->string('reason', 64)->nullable();
            $table->text('comment')->nullable();
            $table->string('provider', 80)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('retrieval_strategy', 80)->nullable();
            $table->unsignedSmallInteger('source_count')->nullable();
            $table->unsignedSmallInteger('citation_count')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'request_id']);
            $table->index(['rating', 'reason']);
            $table->index('request_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_answer_feedback');
    }
};
