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
        Schema::table('evaluation_questions', function (Blueprint $table): void {
            $table->jsonb('intended_references')->nullable()->after('expected_references');
            $table->jsonb('missing_references')->nullable()->after('intended_references');
            $table->string('coverage_status', 40)->default('unknown')->after('expected_source_types')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evaluation_questions', function (Blueprint $table): void {
            $table->dropColumn(['intended_references', 'missing_references', 'coverage_status']);
        });
    }
};
