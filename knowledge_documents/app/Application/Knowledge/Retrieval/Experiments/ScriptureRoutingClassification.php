<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Retrieval\Experiments;

final readonly class ScriptureRoutingClassification
{
    /**
     * @param  list<string>  $references
     * @param  list<string>  $reasons
     */
    public function __construct(
        public string $route,
        public array $references,
        public array $reasons,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'route' => $this->route,
            'references' => $this->references,
            'reasons' => $this->reasons,
        ];
    }
}
