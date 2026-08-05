<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('retrieval_evaluation_runs', function (Blueprint $table): void {
            $table->string('retrieval_strategy', 40)->default('vector')->after('minimum_score')->index();
        });
    }

    public function down(): void
    {
        Schema::table('retrieval_evaluation_runs', function (Blueprint $table): void {
            $table->dropColumn('retrieval_strategy');
        });
    }
};
