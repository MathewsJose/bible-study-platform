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

        DB::statement(<<<'SQL'
            ALTER TABLE knowledge_documents
            ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (
                setweight(to_tsvector('english', coalesce(reference, '')), 'A') ||
                setweight(to_tsvector('english', coalesce(title, '')), 'A') ||
                setweight(to_tsvector('english', coalesce(source_name, '')), 'B') ||
                setweight(to_tsvector('english', coalesce(content, '')), 'C')
            ) STORED
        SQL);

        DB::statement('CREATE INDEX knowledge_documents_search_vector_gin ON knowledge_documents USING gin (search_vector)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS knowledge_documents_search_vector_gin');
        DB::statement('ALTER TABLE knowledge_documents DROP COLUMN IF EXISTS search_vector');
    }
};
