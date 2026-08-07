<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Answering\Services;

use App\Application\Knowledge\Answering\DTOs\CitationData;
use App\Application\Knowledge\Answering\DTOs\PromptData;
use App\Application\Knowledge\Retrieval\DTOs\RetrievalResult;

final readonly class PromptBuilder
{
    /**
     * @param  list<CitationData>  $citations
     * @param  list<array{role: string, content: string}>  $history
     */
    public function build(string $question, RetrievalResult $retrieval, array $citations, array $history = []): PromptData
    {
        $system = (string) config('ai.prompt.system');
        $context = $this->contextBlock($retrieval, $citations);
        $template = (string) config('ai.prompt.template');
        $userPrompt = str_replace(
            ['{question}', '{context}'],
            [$question, $context],
            $template,
        );

        $messages = [
            ['role' => 'system', 'content' => $system],
            ...$history,
            ['role' => 'user', 'content' => $userPrompt],
        ];

        return new PromptData(
            messages: $messages,
            systemInstructions: $system,
            contextBlock: $context,
            estimatedTokens: $this->estimateTokens($system.' '.$userPrompt),
            diagnostics: [
                'context_documents' => count($retrieval->context),
                'citations' => count($citations),
                'template' => 'default',
            ],
        );
    }

    /** @param  list<CitationData>  $citations */
    private function contextBlock(RetrievalResult $retrieval, array $citations): string
    {
        $blocks = [];

        foreach ($retrieval->context as $index => $contextDocument) {
            $number = isset($citations[$index]) ? $citations[$index]->number : ($index + 1);
            $document = $contextDocument->candidate->document;
            $blocks[] = "[{$number}] {$document->reference} ({$document->sourceType}) {$document->title}\n{$document->content}";
        }

        return implode("\n\n", $blocks);
    }

    private function estimateTokens(string $content): int
    {
        return max(1, (int) ceil(str_word_count($content) * 1.35));
    }
}
