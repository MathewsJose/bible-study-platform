<?php

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
        Schema::table('import_manifests', function (Blueprint $table) {
            $table->string('language', 20)->default('en')->after('importer');
            $table->text('rights_notes')->nullable()->after('language');
        });
    }

    public function down(): void
    {
        Schema::table('import_manifests', function (Blueprint $table) {
            $table->dropColumn(['language', 'rights_notes']);
        });
    }
};
