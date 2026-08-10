<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\Tools;

use App\Application\Knowledge\Agents\DTOs\ToolInvocation;
use App\Application\Knowledge\Agents\DTOs\ToolResult;
use App\Application\Knowledge\Answering\Services\AnswerQuestionService;

final readonly class AnswerGenerationTool extends AbstractAgentTool
{
    public function __construct(private AnswerQuestionService $answers) {}

    public function name(): string
    {
        return 'answer_generation';
    }

    public function displayName(): string
    {
        return 'Answer Generation';
    }

    public function description(): string
    {
        return 'Generate a grounded answer through the existing AI Answer Service.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'question' => ['type' => 'string'],
                'profile' => ['type' => 'string'],
                'filters' => ['type' => 'object'],
            ],
            'rules' => [
                'question' => ['required', 'string', 'min:2', 'max:1000'],
                'profile' => ['sometimes', 'string'],
                'filters' => ['sometimes', 'array'],
            ],
        ];
    }

    public function execute(ToolInvocation $invocation): ToolResult
    {
        $started = hrtime(true);
        $answer = $this->answers->answer(
            question: (string) $invocation->arguments['question'],
            profile: isset($invocation->arguments['profile']) ? (string) $invocation->arguments['profile'] : 'ai_answer',
            filters: (array) ($invocation->arguments['filters'] ?? []),
        );

        return $this->success('success', $answer->toArray(), $started);
    }
}
