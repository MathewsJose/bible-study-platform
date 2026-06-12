<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Knowledge\Enums\SourceType;
use App\Domain\Knowledge\Enums\Tradition;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeDocumentRecord>
 */
final class KnowledgeDocumentRecordFactory extends Factory
{
    protected $model = KnowledgeDocumentRecord::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'source_type' => SourceType::Catechism->value,
            'source_name' => 'Catechism of the Catholic Church',
            'tradition' => Tradition::Catholic->value,
            'reference' => 'CCC '.$this->faker->numberBetween(1, 2865),
            'title' => $this->faker->sentence(4),
            'content' => $this->faker->paragraphs(3, true),
            'metadata' => ['language' => 'en'],
        ];
    }
}
