<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests;

use App\Application\Knowledge\Retrieval\Services\RetrievalProfileRepository;
use App\Domain\Knowledge\Enums\SourceType;
use App\Domain\Knowledge\Enums\Tradition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AnswerQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(RetrievalProfileRepository $profiles): array
    {
        return [
            'question' => ['required', 'string', 'min:2', 'max:1000'],
            'profile' => ['sometimes', 'string', Rule::in($profiles->identifiers())],
            'history' => ['sometimes', 'array'],
            'history.*.role' => ['required_with:history', 'string', Rule::in(['user', 'assistant', 'system'])],
            'history.*.content' => ['required_with:history', 'string', 'max:3000'],
            'filters' => ['sometimes', 'array'],
            'filters.source_type' => ['sometimes', 'string', Rule::in(SourceType::values())],
            'filters.source_types' => ['sometimes', 'array'],
            'filters.source_types.*' => ['string', Rule::in(SourceType::values())],
            'filters.source_name' => ['sometimes', 'string'],
            'filters.tradition' => ['sometimes', 'string', Rule::in(Tradition::values())],
            'filters.author' => ['sometimes', 'string'],
            'filters.book' => ['sometimes', 'string'],
            'filters.chapter' => ['sometimes', 'integer'],
            'filters.language' => ['sometimes', 'string'],
            'filters.translation' => ['sometimes', 'string'],
            'filters.century' => ['sometimes', 'string'],
            'filters.theological_topic' => ['sometimes', 'string'],
        ];
    }
}
