<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\Observability\Services;

use App\Application\Knowledge\Agents\DTOs\AgentAction;
use App\Application\Knowledge\Agents\DTOs\AgentRequest;
use App\Application\Knowledge\Agents\DTOs\ToolResult;

final readonly class TracePayloadSanitizer
{
    /** @return array<string, mixed> */
    public function requestMetadata(AgentRequest $request): array
    {
        $storeInputs = (bool) config('agent_observability.tracing.store_inputs', false);
        $metadata = [
            'input_length' => mb_strlen($request->input),
            'filters' => $storeInputs ? $this->sanitize($request->filters) : array_keys($request->filters),
            'allowed_tools' => $request->allowedTools,
            'max_steps' => $request->maxSteps,
            'timeout_seconds' => $request->timeoutSeconds,
            'metadata_keys' => array_keys($request->metadata),
        ];

        if ($storeInputs) {
            $metadata['input'] = $this->redactString($request->input);
            $metadata['metadata'] = $this->sanitize($request->metadata);
        }

        return $metadata;
    }

    /** @return array<string, mixed> */
    public function actionInput(AgentAction $action): array
    {
        $base = [
            'argument_keys' => array_keys($action->arguments),
            'reason' => $action->reason,
        ];

        if ((bool) config('agent_observability.tracing.store_inputs', false)) {
            $base['arguments'] = $this->sanitize($action->arguments);
        }

        return $base;
    }

    /** @return array<string, mixed> */
    public function toolOutput(ToolResult $result): array
    {
        $summary = [
            'successful' => $result->successful,
            'status' => $result->status,
            'warnings' => $this->sanitize($result->warnings),
            'data_keys' => array_keys($result->data),
            'metadata' => $this->sanitize($result->metadata),
        ];

        if (isset($result->data['provider'])) {
            $summary['provider'] = $result->data['provider'];
        }

        if (isset($result->data['model'])) {
            $summary['model'] = $result->data['model'];
        }

        if (isset($result->data['diagnostics'])) {
            $summary['diagnostics'] = $this->sanitize((array) $result->data['diagnostics']);
        }

        if ((bool) config('agent_observability.tracing.store_outputs', false)) {
            $summary['data'] = $this->sanitize($result->data);
        }

        return $summary;
    }

    /**
     * @param  array<mixed>  $payload
     * @return array<mixed>
     */
    public function sanitize(array $payload): array
    {
        $redactedKeys = array_map('strtolower', (array) config('agent_observability.redaction.keys', []));
        $sanitized = [];

        foreach ($payload as $key => $value) {
            $keyString = is_string($key) ? $key : (string) $key;

            if (in_array(mb_strtolower($keyString), $redactedKeys, true)) {
                $sanitized[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize($value);
                continue;
            }

            $sanitized[$key] = is_string($value) ? $this->redactString($value) : $value;
        }

        return $sanitized;
    }

    private function redactString(string $value): string
    {
        $patterns = (array) config('agent_observability.redaction.patterns', []);
        $redacted = $value;

        foreach ($patterns as $pattern) {
            if (! is_string($pattern) || $pattern === '') {
                continue;
            }

            $redacted = (string) preg_replace($pattern, '[REDACTED]', $redacted);
        }

        return $redacted;
    }
}
