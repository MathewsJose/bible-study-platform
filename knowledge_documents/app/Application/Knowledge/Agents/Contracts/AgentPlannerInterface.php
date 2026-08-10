<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\Contracts;

use App\Application\Knowledge\Agents\DTOs\AgentPlan;
use App\Application\Knowledge\Agents\DTOs\AgentState;

interface AgentPlannerInterface
{
    public function plan(AgentState $state): AgentPlan;
}
