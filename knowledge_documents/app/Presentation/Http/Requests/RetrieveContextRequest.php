<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests;

use App\Application\Knowledge\Retrieval\Services\RetrievalProfileRepository;
use App\Domain\Knowledge\Enums\SourceType;
use App\Domain\Knowledge\Enums\Tradition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RetrieveContextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(RetrievalProfileRepository $profiles): array
    {
        return [
            'query' => ['required', 'string', 'min:2', 'max:500'],
            'profile' => ['sometimes', 'string', Rule::in($profiles->identifiers())],
            'top_k' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'context_limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'include_explanations' => ['sometimes', 'boolean'],
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
            'filters.relationship_type' => ['sometimes', 'string'],
        ];
    }
}
