<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Security\Services;

use App\Application\Knowledge\Security\DTOs\PromptInjectionResult;
use Illuminate\Support\Str;

final readonly class PromptInjectionDetector
{
    public function detect(string $input): PromptInjectionResult
    {
        $normalized = $this->normalize($input);
        $signals = [];

        foreach ($this->rules() as $signal => $pattern) {
            if (preg_match($pattern, $normalized) === 1) {
                $signals[] = (string) $signal;
            }
        }

        $score = count($signals);
        $threshold = max(1, (int) config('ai_security.prompt_injection.threshold', 2));

        return new PromptInjectionResult(
            detected: $score >= $threshold,
            score: $score,
            signals: $signals,
        );
    }

    private function normalize(string $input): string
    {
        $lower = Str::lower($input);
        $collapsed = (string) preg_replace('/[\s\p{Z}]+/u', ' ', $lower);

        return trim((string) preg_replace('/[^\p{L}\p{N}\s:\/._-]+/u', '', $collapsed));
    }

    /** @return array<string, string> */
    private function rules(): array
    {
        return [
            'ignore_rules' => '/\b(ignore|disregard|override)\b.{0,80}\b(previous|above|system|developer|security|instructions|rules)\b/',
            'reveal_hidden_instructions' => '/\b(reveal|show|print|dump|repeat|expose)\b.{0,80}\b(system prompt|developer message|hidden instructions|internal instructions|policy)\b/',
            'secret_exfiltration' => '/\b(api key|secret key|bearer token|database password|environment variable|\\.env)\b/',
            'tool_bypass' => '/\b(bypass|disable|circumvent|skip)\b.{0,80}\b(tool restriction|authorization|guardrail|policy|security)\b/',
            'command_execution' => '/\b(run|execute|shell|terminal|powershell|bash|cmd)\b.{0,80}\b(command|script|rm -rf|curl|wget)\b/',
            'role_impersonation' => '/\b(you are now|act as|pretend to be)\b.{0,80}\b(system|developer|administrator|root)\b/',
            'policy_modification' => '/\b(change|modify|update|remove)\b.{0,80}\b(policy|guardrail|security rule|tool permission)\b/',
        ];
    }
}
