<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Security\Services;

use App\Application\Knowledge\Agents\Contracts\ToolInterface;
use App\Application\Knowledge\Security\Contracts\AISecurityPolicyInterface;
use App\Application\Knowledge\Security\DTOs\ApprovalDecision;
use App\Application\Knowledge\Security\DTOs\PiiScanResult;
use App\Application\Knowledge\Security\DTOs\PromptInjectionResult;
use App\Application\Knowledge\Security\DTOs\SecurityEvaluation;
use App\Application\Knowledge\Security\Enums\DataClassification;
use App\Application\Knowledge\Security\Enums\RiskLevel;
use App\Application\Knowledge\Security\Enums\SecurityAction;

final readonly class AISecurityPolicy implements AISecurityPolicyInterface
{
    public function __construct(
        private PiiDetector $pii,
        private PromptInjectionDetector $promptInjection,
        private ResourceLimitPolicy $limits,
        private ToolPolicyCatalog $tools,
        private ProviderPolicy $providers,
        private SecurityEventLogger $events,
    ) {}

    public function evaluateInput(string $input, array $context = []): SecurityEvaluation
    {
        if (! (bool) config('ai_security.enabled', true)) {
            return $this->allowed($input, DataClassification::Public, $this->pii->scan(''), new PromptInjectionResult(false, 0, []));
        }

        $pii = $this->pii->scan($input);
        $injection = $this->promptInjection->detect($input);
        $limitViolations = $this->limits->violationsForInput($input);

        if ($limitViolations !== []) {
            $this->events->record('RESOURCE_LIMIT_EXCEEDED', ['surface' => $context['surface'] ?? null, 'violations' => $limitViolations]);

            return $this->blocked('RESOURCE_LIMIT_EXCEEDED', implode(' ', $limitViolations), $input, $pii, $injection);
        }

        if ($injection->detected && $this->action('prompt_injection.action', SecurityAction::Block) === SecurityAction::Block) {
            $this->events->record('PROMPT_INJECTION_BLOCKED', ['surface' => $context['surface'] ?? null, 'signals' => $injection->signals]);

            return $this->blocked('PROMPT_INJECTION_DETECTED', 'The request was blocked by the AI security policy.', $input, $pii, $injection);
        }

        if ($pii->detected()) {
            $action = $this->action('pii.action', SecurityAction::Redact);

            if ($action === SecurityAction::Block) {
                $this->events->record('PII_POLICY_BLOCKED', ['surface' => $context['surface'] ?? null, 'pii' => $pii->toSafeArray()]);

                return $this->blocked('PII_POLICY_BLOCKED', 'The request contains personal data that is blocked by policy.', $input, $pii, $injection);
            }

            if ($action === SecurityAction::Redact) {
                $this->events->record('PII_REDACTED', ['surface' => $context['surface'] ?? null, 'pii' => $pii->toSafeArray()]);

                return new SecurityEvaluation(
                    allowed: true,
                    status: 'redacted',
                    errorCode: '',
                    message: 'Personal data was redacted before AI processing.',
                    safeInput: $pii->redactedText,
                    classification: DataClassification::Personal,
                    pii: $pii,
                    promptInjection: $injection,
                    warnings: ['PII_REDACTED'],
                );
            }
        }

        return $this->allowed($input, $pii->classification, $pii, $injection);
    }

    public function authorizeTool(ToolInterface $tool, array $arguments = [], array $context = []): SecurityEvaluation
    {
        $policy = $this->tools->policyFor($tool->name());

        if ($policy === null) {
            $this->events->record('TOOL_AUTHORIZATION_DENIED', ['tool' => $tool->name(), 'reason' => 'unknown_tool']);

            return $this->blocked('TOOL_NOT_AUTHORIZED', 'Tool is not authorized by AI security policy.', '', $this->pii->scan(''), new PromptInjectionResult(false, 0, []));
        }

        if (! $policy->readOnly || ! $tool->isReadOnly()) {
            $this->events->record('TOOL_AUTHORIZATION_DENIED', ['tool' => $tool->name(), 'reason' => 'write_tool']);

            return $this->blocked('TOOL_NOT_AUTHORIZED', 'Write tools require an approval workflow.', '', $this->pii->scan(''), new PromptInjectionResult(false, 0, []));
        }

        $limitViolations = $this->limits->violationsForToolArguments($arguments);
        if ($limitViolations !== []) {
            $this->events->record('RESOURCE_LIMIT_EXCEEDED', ['tool' => $tool->name(), 'violations' => $limitViolations]);

            return $this->blocked('RESOURCE_LIMIT_EXCEEDED', implode(' ', $limitViolations), '', $this->pii->scan(''), new PromptInjectionResult(false, 0, []));
        }

        foreach ($arguments as $value) {
            if (! is_string($value)) {
                continue;
            }

            $evaluation = $this->evaluateInput($value, ['surface' => $context['surface'] ?? 'tool', 'tool' => $tool->name()]);
            if (! $evaluation->allowed) {
                return $evaluation;
            }
        }

        $approval = $this->approvalForTool($tool, $context);
        if ($approval->required) {
            $this->events->record('APPROVAL_REQUIRED', $approval->toArray());

            return $this->blocked('APPROVAL_REQUIRED', $approval->reason, '', $this->pii->scan(''), new PromptInjectionResult(false, 0, []));
        }

        return new SecurityEvaluation(
            allowed: true,
            status: 'authorized',
            errorCode: '',
            message: 'Tool authorized.',
            safeInput: '',
            classification: DataClassification::Public,
            pii: $this->pii->scan(''),
            promptInjection: new PromptInjectionResult(false, 0, []),
            metadata: ['tool_policy' => $policy->toArray(), 'approval' => $approval->toArray()],
        );
    }

    public function evaluateProvider(string $provider, array $messages, array $context = []): SecurityEvaluation
    {
        $limitViolations = $this->limits->violationsForMessages($messages);
        if ($limitViolations !== []) {
            $this->events->record('RESOURCE_LIMIT_EXCEEDED', ['provider' => $provider, 'violations' => $limitViolations]);

            return $this->blocked('RESOURCE_LIMIT_EXCEEDED', implode(' ', $limitViolations), '', $this->pii->scan(''), new PromptInjectionResult(false, 0, []));
        }

        if (! $this->providers->externalProcessingAllowed($provider)) {
            $this->events->record('EXTERNAL_PROCESSING_DISABLED', ['provider' => $provider, 'surface' => $context['surface'] ?? null]);

            return $this->blocked('EXTERNAL_PROCESSING_DISABLED', 'External AI processing is disabled by policy.', '', $this->pii->scan(''), new PromptInjectionResult(false, 0, []));
        }

        return $this->allowed('', DataClassification::Internal, $this->pii->scan(''), new PromptInjectionResult(false, 0, []));
    }

    public function approvalForTool(ToolInterface $tool, array $context = []): ApprovalDecision
    {
        $policy = $this->tools->policyFor($tool->name());
        $risk = $policy?->riskLevel ?? RiskLevel::Critical;
        $required = $policy?->requiresApproval ?? true;

        return new ApprovalDecision(
            required: $required,
            reason: $required ? 'Tool risk policy requires human approval.' : 'No approval required for this read-only tool.',
            riskLevel: $risk,
            tool: $tool->name(),
            requestedAction: (string) ($context['requested_action'] ?? 'execute_tool'),
        );
    }

    private function allowed(string $input, DataClassification $classification, PiiScanResult $pii, PromptInjectionResult $injection): SecurityEvaluation
    {
        return new SecurityEvaluation(
            allowed: true,
            status: 'allowed',
            errorCode: '',
            message: 'Allowed.',
            safeInput: $input,
            classification: $classification,
            pii: $pii,
            promptInjection: $injection,
        );
    }

    private function blocked(string $errorCode, string $message, string $input, PiiScanResult $pii, PromptInjectionResult $injection): SecurityEvaluation
    {
        return new SecurityEvaluation(
            allowed: false,
            status: 'blocked',
            errorCode: $errorCode,
            message: $message,
            safeInput: '',
            classification: $pii->classification,
            pii: $pii,
            promptInjection: $injection,
            warnings: [$errorCode],
            metadata: ['input_length' => mb_strlen($input)],
        );
    }

    private function action(string $key, SecurityAction $default): SecurityAction
    {
        return SecurityAction::tryFrom((string) config("ai_security.{$key}", $default->value)) ?? $default;
    }
}
