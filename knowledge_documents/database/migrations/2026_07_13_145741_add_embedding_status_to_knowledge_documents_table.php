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
        Schema::table('knowledge_documents', function (Blueprint $table) {
            $table->string('embedding_status', 20)->default('pending')->index();
            $table->string('embedding_model')->nullable();
            $table->timestampTz('embedded_at')->nullable();
            $table->text('embedding_error')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('knowledge_documents', function (Blueprint $table) {
            $table->dropColumn(['embedding_status', 'embedding_model', 'embedded_at', 'embedding_error']);
        });
    }
};
