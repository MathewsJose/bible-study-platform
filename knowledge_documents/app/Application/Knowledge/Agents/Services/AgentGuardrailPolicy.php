<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\Services;

use App\Application\Knowledge\Agents\Contracts\ToolInterface;
use App\Application\Knowledge\Agents\DTOs\AgentAction;
use App\Application\Knowledge\Agents\DTOs\AgentState;

final readonly class AgentGuardrailPolicy
{
    public function __construct(private ToolInputValidator $validator) {}

    /**
     * @return list<string>
     */
    public function violations(AgentState $state, AgentAction $action, ToolInterface $tool): array
    {
        $violations = [];
        $allowedTools = $state->request->allowedTools !== []
            ? $state->request->allowedTools
            : $state->profile->allowedTools;

        if (! in_array($tool->name(), $allowedTools, true)) {
            $violations[] = "Tool [{$tool->name()}] is not allowed for this agent run.";
        }

        if (! $tool->isReadOnly()) {
            $violations[] = "Tool [{$tool->name()}] is not read-only.";
        }

        if ($state->currentStep >= ($state->request->maxSteps ?? $state->profile->maxSteps)) {
            $violations[] = 'Maximum agent steps reached.';
        }

        if (count($state->toolResults) >= $state->profile->maxToolCalls) {
            $violations[] = 'Maximum tool calls reached.';
        }

        if ($state->hasExecuted($action)) {
            $violations[] = 'Duplicate tool call detected.';
        }

        if ($state->elapsedMs() > (($state->request->timeoutSeconds ?? $state->profile->timeoutSeconds) * 1000)) {
            $violations[] = 'Agent timeout reached.';
        }

        return [...$violations, ...$this->validator->errors($action->arguments, $tool->inputSchema())];
    }
}
