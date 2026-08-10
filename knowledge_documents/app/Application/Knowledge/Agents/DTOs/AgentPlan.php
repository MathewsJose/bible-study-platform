<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\DTOs;

final readonly class AgentPlan
{
    /**
     * @param  list<AgentAction>  $actions
     */
    public function __construct(
        public array $actions,
        public bool $complete = false,
        public ?string $finalAnswer = null,
        public string $decision = '',
    ) {}
}
