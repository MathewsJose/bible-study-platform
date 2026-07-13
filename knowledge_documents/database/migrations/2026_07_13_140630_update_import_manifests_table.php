<?php


declare(strict_types=1);


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_manifests', function (Blueprint $table): void {
            // Renames
            $table->renameColumn('file_hash', 'checksum');
            $table->renameColumn('file_type', 'source_type');
            $table->renameColumn('imported_records', 'records_created');
            $table->renameColumn('skipped_records', 'records_skipped');
            $table->renameColumn('failed_records', 'records_failed');
            $table->renameColumn('imported_at', 'finished_at');
            $table->timestamp('finished_at')->nullable()->change();

            // Add new fields
            $table->string('source_url')->nullable()->after('source_name');
            $table->string('license')->nullable()->after('source_url');
            $table->string('license_url')->nullable()->after('license');
            $table->string('importer')->after('license_url');
            $table->string('status')->after('importer'); // running, completed, failed
            $table->unsignedInteger('records_updated')->default(0)->after('records_created');
            $table->timestamp('started_at')->nullable()->after('status');
            $table->text('error_message')->nullable()->after('finished_at');
        });

        // Set default started_at for existing records if any
        DB::table('import_manifests')->update(['started_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('import_manifests', function (Blueprint $table): void {
            $table->dropColumn([
                'source_url',
                'license',
                'license_url',
                'importer',
                'status',
                'records_updated',
                'started_at',
                'error_message',
            ]);

            $table->renameColumn('checksum', 'file_hash');
            $table->renameColumn('source_type', 'file_type');
            $table->renameColumn('records_created', 'imported_records');
            $table->renameColumn('records_skipped', 'skipped_records');
            $table->renameColumn('records_failed', 'failed_records');
            $table->renameColumn('finished_at', 'imported_at');
        });
    }
};
