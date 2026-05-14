<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use MongoDB\Collection;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->collection('verses')->createIndex(
            ['language' => 1, 'version' => 1, 'book' => 1, 'chapter' => 1, 'verse' => 1],
            ['name' => 'verses_reference_lookup_idx']
        );

        $this->collection('historical_contexts')->createIndex(
            ['language' => 1, 'version' => 1, 'book' => 1, 'chapter' => 1, 'verse' => 1],
            ['name' => 'historical_contexts_reference_lookup_idx']
        );

        $this->collection('church_teachings')->createIndex(
            ['language' => 1, 'version' => 1, 'book' => 1, 'chapter' => 1, 'verse' => 1],
            ['name' => 'church_teachings_reference_lookup_idx']
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropIndexIfExists('verses', 'verses_reference_lookup_idx');
        $this->dropIndexIfExists('historical_contexts', 'historical_contexts_reference_lookup_idx');
        $this->dropIndexIfExists('church_teachings', 'church_teachings_reference_lookup_idx');
    }

    private function collection(string $name): Collection
    {
        return DB::connection('mongodb')->getMongoDB()->selectCollection($name);
    }

    private function dropIndexIfExists(string $collection, string $index): void
    {
        $mongoCollection = $this->collection($collection);

        foreach ($mongoCollection->listIndexes() as $existingIndex) {
            if ($existingIndex->getName() === $index) {
                $mongoCollection->dropIndex($index);

                return;
            }
        }
    }
};
