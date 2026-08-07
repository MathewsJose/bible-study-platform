<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Retrieval\DTOs;

final readonly class AnalyzedQuery
{
    /**
     * @param  list<string>  $intents
     * @param  list<string>  $references
     * @param  list<string>  $topics
     */
    public function __construct(
        public string $query,
        public array $intents,
        public array $references = [],
        public array $topics = [],
        public bool $isQuestion = false,
    ) {}

    public function primaryIntent(): string
    {
        return $this->intents[0] ?? 'natural_language';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'query' => $this->query,
            'intents' => $this->intents,
            'primary_intent' => $this->primaryIntent(),
            'references' => $this->references,
            'topics' => $this->topics,
            'is_question' => $this->isQuestion,
        ];
    }
}
