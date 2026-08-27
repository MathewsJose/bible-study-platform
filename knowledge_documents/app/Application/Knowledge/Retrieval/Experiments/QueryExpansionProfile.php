<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Retrieval\Experiments;

final readonly class QueryExpansionProfile
{
    /**
     * @param  list<string>  $triggers
     * @param  list<string>  $terms
     */
    public function __construct(
        public string $identifier,
        public string $label,
        public array $triggers,
        public array $terms,
        public string $reason,
    ) {}

    /**
     * @param  array<string, mixed>  $profile
     */
    public static function fromConfig(array $profile): self
    {
        return new self(
            identifier: (string) $profile['identifier'],
            label: (string) $profile['label'],
            triggers: array_values(array_map('strval', $profile['triggers'] ?? [])),
            terms: array_values(array_map('strval', $profile['terms'] ?? [])),
            reason: (string) $profile['reason'],
        );
    }
}
