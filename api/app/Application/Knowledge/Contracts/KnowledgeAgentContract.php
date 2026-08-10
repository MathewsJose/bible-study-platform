<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Contracts;

use App\Application\Knowledge\DTOs\KnowledgeServiceResult;

interface KnowledgeAgentContract
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function runAgent(array $payload, ?string $requestId = null): KnowledgeServiceResult;

    public function agentExecution(string $executionId, ?string $requestId = null): KnowledgeServiceResult;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function replayAgentExecution(string $executionId, array $payload = [], ?string $requestId = null): KnowledgeServiceResult;

    public function agentReplay(string $replayId, ?string $requestId = null): KnowledgeServiceResult;
}
