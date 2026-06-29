<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'score_threshold' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
