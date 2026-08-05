<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS knowledge_documents_embedding_hnsw');
        DB::statement('ALTER TABLE knowledge_documents DROP COLUMN embedding');
        DB::statement('ALTER TABLE knowledge_documents ADD COLUMN embedding vector(384) NULL');
        DB::statement('CREATE INDEX knowledge_documents_embedding_hnsw ON knowledge_documents USING hnsw (embedding vector_cosine_ops)');
        DB::table('knowledge_documents')->update([
            'embedding_status' => 'pending',
            'embedding_model' => null,
            'embedding_provider' => null,
            'embedding_dimensions' => null,
            'embedded_at' => null,
            'embedding_error' => 'Existing embeddings cleared because local model sentence-transformers/all-MiniLM-L6-v2 uses 384 dimensions.',
        ]);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS knowledge_documents_embedding_hnsw');
        DB::statement('ALTER TABLE knowledge_documents DROP COLUMN embedding');
        DB::statement('ALTER TABLE knowledge_documents ADD COLUMN embedding vector(1536) NULL');
        DB::statement('CREATE INDEX knowledge_documents_embedding_hnsw ON knowledge_documents USING hnsw (embedding vector_cosine_ops)');
        DB::table('knowledge_documents')->update([
            'embedding_status' => 'pending',
            'embedding_model' => null,
            'embedding_provider' => null,
            'embedding_dimensions' => null,
            'embedded_at' => null,
            'embedding_error' => 'Existing embeddings cleared because vector column was restored to 1536 dimensions.',
        ]);
    }
};
