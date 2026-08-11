<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Security\Contracts;

use App\Application\Knowledge\Agents\Contracts\ToolInterface;
use App\Application\Knowledge\Security\DTOs\ApprovalDecision;
use App\Application\Knowledge\Security\DTOs\SecurityEvaluation;

interface AISecurityPolicyInterface
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function evaluateInput(string $input, array $context = []): SecurityEvaluation;

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $context
     */
    public function authorizeTool(ToolInterface $tool, array $arguments = [], array $context = []): SecurityEvaluation;

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $context
     */
    public function evaluateProvider(string $provider, array $messages, array $context = []): SecurityEvaluation;

    /**
     * @param  array<string, mixed>  $context
     */
    public function approvalForTool(ToolInterface $tool, array $context = []): ApprovalDecision;
}
