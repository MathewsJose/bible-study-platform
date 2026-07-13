<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_manifests', function (Blueprint $table): void {
            $table->id();
            $table->string('file_path');
            $table->string('file_hash');
            $table->string('file_type');
            $table->string('source_name');
            $table->unsignedInteger('total_records');
            $table->unsignedInteger('imported_records');
            $table->unsignedInteger('skipped_records');
            $table->unsignedInteger('failed_records');
            $table->timestamp('imported_at');
            $table->timestamps();

            $table->index(['file_hash', 'file_type']);
            $table->index('file_path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_manifests');
    }
};
