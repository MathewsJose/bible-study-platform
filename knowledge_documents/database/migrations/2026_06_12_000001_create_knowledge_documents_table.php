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
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        }

        Schema::create('knowledge_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('source_type', 40);
            $table->string('source_name');
            $table->string('tradition', 80);
            $table->string('reference');
            $table->string('title');
            $table->text('content');
            $table->jsonb('metadata')->default('{}');
            $table->timestampsTz();

            $table->index(['source_type', 'reference']);
            $table->index('tradition');
            $table->index('source_name');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE knowledge_documents ADD COLUMN embedding vector(1536) NULL');
            DB::statement('CREATE INDEX knowledge_documents_metadata_gin ON knowledge_documents USING gin (metadata)');
            DB::statement("CREATE INDEX knowledge_documents_fts ON knowledge_documents USING gin (to_tsvector('english', title || ' ' || content || ' ' || reference))");
            DB::statement('CREATE INDEX knowledge_documents_embedding_hnsw ON knowledge_documents USING hnsw (embedding vector_cosine_ops)');
        } else {
            Schema::table('knowledge_documents', function (Blueprint $table): void {
                $table->json('embedding')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_documents');
    }
};
