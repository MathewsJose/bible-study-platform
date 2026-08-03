<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests;

use App\Domain\Knowledge\Enums\SourceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SearchKnowledgeDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'min:2', 'max:500'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.((int) config('knowledge.semantic_search.max_limit', 50))],
            'top_k' => ['sometimes', 'integer', 'min:1', 'max:'.((int) config('knowledge.semantic_search.max_limit', 50))],
            'score_threshold' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'source_type' => ['sometimes', 'string', Rule::in(SourceType::values())],
            'source_types' => ['sometimes', 'array'],
            'source_types.*' => ['string', Rule::in(SourceType::values())],
            'source_name' => ['sometimes', 'string'],
            'tradition' => ['sometimes', 'string'],
            'book' => ['sometimes', 'string'],
            'chapter' => ['sometimes', 'integer'],
        ];
    }
}
