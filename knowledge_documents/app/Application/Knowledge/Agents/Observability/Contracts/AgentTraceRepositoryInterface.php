<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\Observability\Contracts;

use App\Application\Knowledge\Agents\DTOs\AgentAction;
use App\Application\Knowledge\Agents\DTOs\AgentState;
use App\Application\Knowledge\Agents\DTOs\ToolResult;
use App\Application\Knowledge\Agents\Observability\DTOs\AgentTraceData;
use App\Infrastructure\Knowledge\Agents\Persistence\AgentExecutionRecord;
use App\Infrastructure\Knowledge\Agents\Persistence\AgentExecutionStepRecord;

interface AgentTraceRepositoryInterface
{
    public function startExecution(AgentState $state): ?AgentExecutionRecord;

    public function recordStepStarted(AgentExecutionRecord $execution, AgentState $state, AgentAction $action): ?AgentExecutionStepRecord;

    public function recordStepCompleted(AgentExecutionStepRecord $step, ToolResult $result): void;

    public function completeExecution(AgentState $state): void;

    public function failExecution(AgentState $state, string $failureCategory): void;

    public function find(string $executionId): ?AgentTraceData;

    /** @return array<string, mixed> */
    public function health(?int $days = null): array;

    public function pruneOlderThan(int $days, int $limit): int;
}
