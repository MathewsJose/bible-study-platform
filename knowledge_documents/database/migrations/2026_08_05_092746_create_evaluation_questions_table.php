<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_questions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('question');
            $table->jsonb('expected_references');
            $table->jsonb('expected_source_types');
            $table->text('notes')->nullable();
            $table->string('category')->nullable()->index();
            $table->timestampsTz();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_questions');
    }
};
