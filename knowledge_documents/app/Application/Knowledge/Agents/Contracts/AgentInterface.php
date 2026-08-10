<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\Contracts;

use App\Application\Knowledge\Agents\DTOs\AgentPlan;
use App\Application\Knowledge\Agents\DTOs\AgentRequest;
use App\Application\Knowledge\Agents\DTOs\AgentResponse;
use App\Application\Knowledge\Agents\DTOs\AgentState;
use App\Application\Knowledge\Agents\DTOs\ToolResult;

interface AgentInterface
{
    public function execute(AgentRequest $request): AgentResponse;

    public function plan(AgentState $state): AgentPlan;

    public function observe(AgentState $state, ToolResult $result): AgentState;

    public function finalize(AgentState $state): AgentResponse;
}
