<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Evaluation\Contracts;

use App\Application\Knowledge\Answering\DTOs\AnswerData;
use App\Application\Knowledge\Evaluation\DTOs\AnswerEvaluation;
use App\Infrastructure\Knowledge\Persistence\EvaluationQuestionRecord;

interface AnswerEvaluatorInterface
{
    public function evaluate(AnswerData $answer, EvaluationQuestionRecord $question): AnswerEvaluation;
}
