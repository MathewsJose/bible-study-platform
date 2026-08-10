<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\Replay\Services;

use App\Application\Knowledge\Agents\Replay\DTOs\ReplayComparisonResult;
use App\Infrastructure\Knowledge\Agents\Persistence\AgentExecutionRecord;

final readonly class ReplayComparisonService
{
    public function compare(AgentExecutionRecord $original, ?AgentExecutionRecord $replay, array $currentFingerprint): ReplayComparisonResult
    {
        $originalFingerprint = $this->originalFingerprint($original);
        $environment = [
            'execution_fingerprint' => $this->matchStatus($originalFingerprint['hash'] ?? null, $currentFingerprint['hash'] ?? null),
            'corpus' => $this->matchStatus($originalFingerprint['corpus']['hash'] ?? null, $currentFingerprint['corpus']['hash'] ?? null),
            'agent_profile' => $this->matchStatus($original->profile, $replay?->profile ?? $original->profile),
            'provider' => $this->matchStatus($original->provider, $replay?->provider),
            'model' => $this->matchStatus($original->model, $replay?->model),
        ];

        $originalTools = $this->toolSequence($original);
        $replayTools = $replay === null ? [] : $this->toolSequence($replay);
        $toolSequenceStatus = $replay === null ? 'NOT_REPLAYED' : ($originalTools === $replayTools ? 'MATCH' : 'DIFFERENT');

        $retrieval = $this->compareDocumentReferences($original, $replay, 'supporting_documents');
        $citations = $this->compareDocumentReferences($original, $replay, 'citations');
        $answer = $this->compareAnswer($original, $replay);
        $latency = $this->latency($original, $replay);
        $possibleCauses = $this->possibleCauses($environment, $toolSequenceStatus, $retrieval['status'], $citations['status'], $answer['status']);

        $status = $possibleCauses === [] ? 'MATCH' : 'DIFFERENT';

        return new ReplayComparisonResult(
            status: $status,
            environment: $environment,
            toolSequenceStatus: $toolSequenceStatus,
            toolSequence: [
                ['label' => 'original', 'tools' => $originalTools],
                ['label' => 'replay', 'tools' => $replayTools],
            ],
            retrieval: $retrieval,
            citations: $citations,
            answer: $answer,
            possibleCauses: $possibleCauses,
            latency: $latency,
        );
    }

    /** @return array<string, mixed> */
    private function originalFingerprint(AgentExecutionRecord $original): array
    {
        $metadata = is_array($original->metadata) ? $original->metadata : [];
        $replay = is_array($metadata['replay'] ?? null) ? $metadata['replay'] : [];

        return is_array($replay['execution_fingerprint'] ?? null) ? $replay['execution_fingerprint'] : [];
    }

    /** @return list<string> */
    private function toolSequence(AgentExecutionRecord $execution): array
    {
        return $execution->steps()
            ->orderBy('step_number')
            ->pluck('tool_name')
            ->map(static fn (mixed $tool): string => (string) $tool)
            ->all();
    }

    /** @return array<string, mixed> */
    private function compareDocumentReferences(AgentExecutionRecord $original, ?AgentExecutionRecord $replay, string $key): array
    {
        $originalReferences = $this->referencesFromAnswerData($original, $key);
        $replayReferences = $replay === null ? [] : $this->referencesFromAnswerData($replay, $key);

        return [
            'status' => $replay === null ? 'NOT_REPLAYED' : ($originalReferences === $replayReferences ? 'MATCH' : 'DIFFERENT'),
            'original' => $originalReferences,
            'replay' => $replayReferences,
            'added' => array_values(array_diff($replayReferences, $originalReferences)),
            'removed' => array_values(array_diff($originalReferences, $replayReferences)),
        ];
    }

    /** @return list<string> */
    private function referencesFromAnswerData(AgentExecutionRecord $execution, string $key): array
    {
        $answerStep = $execution->steps()->where('tool_name', 'answer_generation')->latest()->first();
        $data = is_array($answerStep?->output_metadata['data'] ?? null) ? $answerStep->output_metadata['data'] : [];
        $items = is_array($data[$key] ?? null) ? $data[$key] : [];

        return collect($items)
            ->map(static fn (mixed $item): string => is_array($item) ? (string) ($item['reference'] ?? $item['document_reference'] ?? $item['id'] ?? '') : '')
            ->filter(static fn (string $reference): bool => $reference !== '')
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function compareAnswer(AgentExecutionRecord $original, ?AgentExecutionRecord $replay): array
    {
        $originalAnswer = $this->answerText($original);
        $replayAnswer = $replay === null ? '' : $this->answerText($replay);

        $status = match (true) {
            $replay === null => 'NOT_REPLAYED',
            $originalAnswer === $replayAnswer => 'IDENTICAL',
            $this->normalizeText($originalAnswer) === $this->normalizeText($replayAnswer) => 'STRUCTURALLY_SIMILAR',
            default => 'SIGNIFICANT_DIFFERENCE',
        };

        return [
            'status' => $status,
            'original_length' => mb_strlen($originalAnswer),
            'replay_length' => mb_strlen($replayAnswer),
            'exact_model_replay_guaranteed' => false,
        ];
    }

    private function answerText(AgentExecutionRecord $execution): string
    {
        $answerStep = $execution->steps()->where('tool_name', 'answer_generation')->latest()->first();
        $data = is_array($answerStep?->output_metadata['data'] ?? null) ? $answerStep->output_metadata['data'] : [];

        return is_string($data['answer'] ?? null) ? $data['answer'] : '';
    }

    private function normalizeText(string $text): string
    {
        return trim(mb_strtolower(preg_replace('/\s+/u', ' ', $text) ?? $text));
    }

    /** @return array<string, mixed> */
    private function latency(AgentExecutionRecord $original, ?AgentExecutionRecord $replay): array
    {
        $originalMs = $original->duration_ms;
        $replayMs = $replay?->duration_ms;

        return [
            'original_ms' => $originalMs,
            'replay_ms' => $replayMs,
            'change_percent' => $replayMs === null || $originalMs === 0 ? null : round((($replayMs - $originalMs) / $originalMs) * 100, 2),
        ];
    }

    /** @return list<string> */
    private function possibleCauses(array $environment, string $toolSequence, string $retrieval, string $citations, string $answer): array
    {
        $causes = [];

        if (($environment['corpus'] ?? 'UNKNOWN') !== 'MATCH') {
            $causes[] = 'Corpus changed.';
        }

        if (($environment['execution_fingerprint'] ?? 'UNKNOWN') !== 'MATCH') {
            $causes[] = 'Agent, retrieval, tool, provider, model, prompt, or corpus configuration changed.';
        }

        if ($toolSequence === 'DIFFERENT') {
            $causes[] = 'Agent planning or tool availability changed.';
        }

        if ($retrieval === 'DIFFERENT') {
            $causes[] = 'Retrieval selected a different document set or ranking.';
        }

        if ($citations === 'DIFFERENT') {
            $causes[] = 'Answer citations changed.';
        }

        if ($answer === 'SIGNIFICANT_DIFFERENCE') {
            $causes[] = 'Provider/model output differs; exact LLM replay is not guaranteed.';
        }

        return array_values(array_unique($causes));
    }

    private function matchStatus(mixed $original, mixed $replay): string
    {
        if ($original === null || $original === '' || $replay === null || $replay === '') {
            return 'UNKNOWN';
        }

        return $original === $replay ? 'MATCH' : 'DIFFERENT';
    }
}
