<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\Services;

use App\Application\Knowledge\Agents\Contracts\AgentPlannerInterface;
use App\Application\Knowledge\Agents\DTOs\AgentPlan;
use App\Application\Knowledge\Agents\DTOs\AgentState;

final readonly class LLMAgentPlanner implements AgentPlannerInterface
{
    public function __construct(private DeterministicAgentPlanner $fallback) {}

    public function plan(AgentState $state): AgentPlan
    {
        return $this->fallback->plan($state);
    }
}
