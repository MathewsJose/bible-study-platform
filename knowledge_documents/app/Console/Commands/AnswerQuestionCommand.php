<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Answering\DTOs\CitationData;
use App\Application\Knowledge\Answering\Services\AnswerQuestionService;
use Illuminate\Console\Command;

final class AnswerQuestionCommand extends Command
{
    protected $signature = 'ai:answer
                            {question : Question to answer}
                            {--profile=ai_answer : Retrieval profile}';

    protected $description = 'Answer a question using retrieved Catholic knowledge and the configured LLM provider.';

    public function handle(AnswerQuestionService $answers): int
    {
        $answer = $answers->answer(
            question: (string) $this->argument('question'),
            profile: (string) $this->option('profile'),
        );

        $this->line('AI Answer');
        $this->line($answer->answer);
        $this->line('');
        $this->line('Confidence: '.$answer->confidence->score);
        $this->line('Provider: '.$answer->provider.' / '.$answer->model);
        $this->table(
            ['#', 'Reference', 'Source'],
            array_map(
                static fn (CitationData $citation): array => [$citation->number, $citation->reference, $citation->sourceType],
                $answer->citations,
            ),
        );

        if ($answer->warnings !== []) {
            $this->warn('Warnings: '.implode(' | ', $answer->warnings));
        }

        return self::SUCCESS;
    }
}
