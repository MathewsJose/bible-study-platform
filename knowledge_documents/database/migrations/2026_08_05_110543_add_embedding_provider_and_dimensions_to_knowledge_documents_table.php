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
        Schema::table('knowledge_documents', function (Blueprint $table): void {
            $table->string('embedding_provider', 40)->nullable()->after('embedding_model')->index();
            $table->unsignedInteger('embedding_dimensions')->nullable()->after('embedding_provider')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('knowledge_documents', function (Blueprint $table): void {
            $table->dropColumn(['embedding_provider', 'embedding_dimensions']);
        });
    }
};
