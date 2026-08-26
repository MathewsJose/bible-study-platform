<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retrieval_contextual_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_document_id')->constrained('knowledge_documents')->cascadeOnDelete();
            $table->string('source_type', 40);
            $table->string('source_name');
            $table->string('reference');
            $table->string('book')->nullable();
            $table->unsignedInteger('chapter')->nullable();
            $table->unsignedInteger('verse')->nullable();
            $table->string('document_type', 40);
            $table->string('context_window', 40);
            $table->longText('context_text');
            $table->string('context_checksum', 64);
            $table->string('embedding_provider', 40)->nullable();
            $table->string('embedding_model')->nullable();
            $table->unsignedInteger('embedding_dimensions')->nullable();
            $table->timestampTz('embedded_at')->nullable();
            $table->text('embedding_error')->nullable();
            $table->timestampsTz();

            $table->unique(['source_document_id', 'context_window'], 'contextual_docs_source_window_unique');
            $table->index(['context_window', 'source_type']);
            $table->index(['reference', 'context_window']);
            $table->index(['embedding_model', 'embedding_dimensions']);
            $table->index('context_checksum');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE retrieval_contextual_documents ADD COLUMN embedding vector(384) NULL');
            DB::statement('CREATE INDEX retrieval_contextual_documents_embedding_hnsw ON retrieval_contextual_documents USING hnsw (embedding vector_cosine_ops)');
        } else {
            Schema::table('retrieval_contextual_documents', function (Blueprint $table): void {
                $table->json('embedding')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('retrieval_contextual_documents');
    }
};
