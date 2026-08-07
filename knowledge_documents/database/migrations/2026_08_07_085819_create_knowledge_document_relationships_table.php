<?php

declare(strict_types=1);

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
        Schema::create('knowledge_document_relationships', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_document_id')->constrained('knowledge_documents')->cascadeOnDelete();
            $table->foreignUuid('target_document_id')->constrained('knowledge_documents')->cascadeOnDelete();
            $table->string('relationship_type', 80);
            $table->decimal('confidence', 5, 4)->default(1.0);
            $table->jsonb('provenance')->default('{}');
            $table->jsonb('metadata')->default('{}');
            $table->timestampsTz();

            $table->unique(
                ['source_document_id', 'target_document_id', 'relationship_type'],
                'knowledge_relationships_unique_edge',
            );
            $table->index(['source_document_id', 'relationship_type'], 'knowledge_relationships_source_type_index');
            $table->index(['target_document_id', 'relationship_type'], 'knowledge_relationships_target_type_index');
            $table->index('relationship_type', 'knowledge_relationships_type_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledge_document_relationships');
    }
};
