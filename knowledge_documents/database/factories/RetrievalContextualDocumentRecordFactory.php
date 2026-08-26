<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Knowledge\Persistence\RetrievalContextualDocumentRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RetrievalContextualDocumentRecord>
 */
final class RetrievalContextualDocumentRecordFactory extends Factory
{
    protected $model = RetrievalContextualDocumentRecord::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'source_document_id' => $this->faker->uuid(),
            'source_type' => 'bible_verse',
            'source_name' => 'Douay-Rheims Bible',
            'reference' => 'John '.$this->faker->numberBetween(1, 21).':'.$this->faker->numberBetween(1, 50),
            'book' => 'John',
            'chapter' => 1,
            'verse' => 1,
            'document_type' => 'bible_verse',
            'context_window' => 'verse',
            'context_text' => $this->faker->paragraph(),
            'context_checksum' => hash('sha256', $this->faker->uuid()),
        ];
    }
}
