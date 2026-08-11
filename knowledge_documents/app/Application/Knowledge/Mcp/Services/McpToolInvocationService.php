<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Mcp\Services;

use App\Application\Knowledge\Agents\DTOs\ToolInvocation;
use App\Application\Knowledge\Agents\DTOs\ToolResult;
use App\Application\Knowledge\Agents\Observability\Services\FailureClassifier;
use App\Application\Knowledge\Agents\Observability\Services\TracePayloadSanitizer;
use App\Application\Knowledge\Agents\Services\ToolInputValidator;
use App\Application\Knowledge\Security\Contracts\AISecurityPolicyInterface;
use App\Infrastructure\Knowledge\Agents\Persistence\AgentExecutionRecord;
use App\Infrastructure\Knowledge\Agents\Persistence\AgentExecutionStepRecord;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final readonly class McpToolInvocationService
{
    public function __construct(
        private McpToolCatalog $catalog,
        private ToolInputValidator $validator,
        private TracePayloadSanitizer $sanitizer,
        private FailureClassifier $failures,
        private AISecurityPolicyInterface $security,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $context
     */
    public function invoke(string $toolName, array $arguments, array $context = []): ToolResult
    {
        $tool = $this->catalog->tool($toolName);

        if (! $tool->isReadOnly()) {
            return $this->failure($toolName, 'forbidden', 'MCP only exposes read-only tools.');
        }

        $security = $this->security->authorizeTool($tool, $arguments, [
            'surface' => 'mcp',
            'request_id' => $context['request_id'] ?? null,
            'mcp_request_id' => $context['mcp_request_id'] ?? null,
        ]);

        if (! $security->allowed) {
            return $this->failure($toolName, $security->status, $security->message, $security->errorCode, $security->diagnostics());
        }

        $safeArguments = $this->safeArguments($arguments);
        $errors = $this->validator->errors($safeArguments, $tool->inputSchema());

        if ($errors !== []) {
            return new ToolResult(
                tool: $toolName,
                successful: false,
                status: 'invalid_arguments',
                warnings: $errors,
                error: implode(' ', $errors),
            );
        }

        $requestId = $this->requestId($context);
        $agentId = Str::uuid()->toString();
        $execution = $this->startTrace($agentId, $requestId, $toolName, $safeArguments, $context);
        $step = $execution instanceof AgentExecutionRecord ? $this->startStep($execution, $toolName, $safeArguments, $context) : null;

        try {
            $result = $tool->execute(new ToolInvocation(
                agentId: $agentId,
                requestId: $requestId,
                tool: $toolName,
                arguments: $safeArguments,
                context: [
                    'profile' => 'mcp',
                    'mcp_request_id' => is_string($context['mcp_request_id'] ?? null) ? $context['mcp_request_id'] : null,
                ],
            ));
        } catch (Throwable $throwable) {
            report($throwable);

            $result = $this->failure($toolName, 'failed', 'MCP tool execution failed.', 'AI_SECURITY_TOOL_FAILED');
        }

        $this->completeStep($step, $result);
        $this->completeTrace($execution, $result);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function failure(string $tool, string $status, string $message, string $errorCode = 'AI_SECURITY_BLOCKED', array $metadata = []): ToolResult
    {
        return new ToolResult(
            tool: $tool,
            successful: false,
            status: $status,
            metadata: ['error_code' => $errorCode, ...$metadata],
            error: $message,
        );
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function safeArguments(array $arguments): array
    {
        $safe = [];

        foreach ($arguments as $key => $value) {
            if (! is_string($value)) {
                $safe[$key] = $value;
                continue;
            }

            $evaluation = $this->security->evaluateInput($value, ['surface' => 'mcp_argument']);
            if (! $evaluation->allowed) {
                $safe[$key] = $value;
                continue;
            }

            $safe[$key] = $evaluation->safeInput;
        }

        return $safe;
    }

    /** @param array<string, mixed> $context */
    private function requestId(array $context): string
    {
        return is_string($context['request_id'] ?? null) && $context['request_id'] !== ''
            ? $context['request_id']
            : Str::uuid()->toString();
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $context
     */
    private function startTrace(string $agentId, string $requestId, string $toolName, array $arguments, array $context): ?AgentExecutionRecord
    {
        if (! $this->traceReady()) {
            return null;
        }

        return AgentExecutionRecord::query()->create([
            'id' => $agentId,
            'request_id' => $requestId,
            'profile' => 'mcp',
            'status' => 'running',
            'started_at' => CarbonImmutable::now(),
            'input_metadata' => $this->inputMetadata($arguments, $context),
            'metadata' => [
                'source' => 'mcp',
                'mcp' => [
                    'request_id' => $context['mcp_request_id'] ?? null,
                    'tool' => $toolName,
                    'transport' => config('mcp_knowledge.transport', 'http'),
                ],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $context
     */
    private function startStep(AgentExecutionRecord $execution, string $toolName, array $arguments, array $context): AgentExecutionStepRecord
    {
        return AgentExecutionStepRecord::query()->create([
            'agent_execution_id' => $execution->id,
            'step_number' => 1,
            'action_type' => 'mcp_tool',
            'tool_name' => $toolName,
            'status' => 'running',
            'started_at' => CarbonImmutable::now(),
            'input_metadata' => $this->inputMetadata($arguments, $context),
            'metadata' => ['mcp_request_id' => $context['mcp_request_id'] ?? null],
        ]);
    }

    private function completeStep(?AgentExecutionStepRecord $step, ToolResult $result): void
    {
        if (! $step instanceof AgentExecutionStepRecord) {
            return;
        }

        $step->update([
            'status' => $result->status,
            'failure_category' => $result->successful ? null : $this->failures->classifyToolResult($result),
            'completed_at' => CarbonImmutable::now(),
            'duration_ms' => $result->latencyMs,
            'output_metadata' => $this->outputMetadata($result),
            'validation_errors' => $result->warnings,
            'error_information' => $result->error === null ? null : ['message' => $this->sanitizer->sanitize(['error' => $result->error])['error']],
        ]);
    }

    private function completeTrace(?AgentExecutionRecord $execution, ToolResult $result): void
    {
        if (! $execution instanceof AgentExecutionRecord) {
            return;
        }

        $execution->update([
            'status' => $result->successful ? 'completed' : 'failed',
            'failure_category' => $result->successful ? null : $this->failures->classifyToolResult($result),
            'completed_at' => CarbonImmutable::now(),
            'duration_ms' => $result->latencyMs,
            'step_count' => 1,
            'tool_call_count' => 1,
            'error_information' => $result->error === null ? null : ['message' => $this->sanitizer->sanitize(['error' => $result->error])['error']],
        ]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function inputMetadata(array $arguments, array $context): array
    {
        $metadata = [
            'argument_keys' => array_keys($arguments),
            'mcp_request_id' => $context['mcp_request_id'] ?? null,
        ];

        if ((bool) config('agent_observability.tracing.store_inputs', false)) {
            $metadata['arguments'] = $this->sanitizer->sanitize($arguments);
        }

        return $metadata;
    }

    /** @return array<string, mixed> */
    private function outputMetadata(ToolResult $result): array
    {
        $metadata = [
            'successful' => $result->successful,
            'status' => $result->status,
            'warnings' => $this->sanitizer->sanitize($result->warnings),
            'data_keys' => array_keys($result->data),
        ];

        if ((bool) config('agent_observability.tracing.store_outputs', false)) {
            $metadata['data'] = $this->sanitizer->sanitize($result->data);
        }

        return $metadata;
    }

    private function traceReady(): bool
    {
        return (bool) config('agent_observability.tracing.enabled', true)
            && Schema::hasTable('agent_executions')
            && Schema::hasTable('agent_execution_steps');
    }
}
