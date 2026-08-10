<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests;

use App\Domain\Knowledge\Enums\SourceType;
use App\Domain\Knowledge\Enums\Tradition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class KnowledgeIntegrationSearchRequest extends FormRequest
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
            'source_type' => ['sometimes', 'string', Rule::in(SourceType::values())],
            'book' => ['sometimes', 'string', 'max:80'],
            'chapter' => ['sometimes', 'integer', 'min:1'],
            'translation' => ['sometimes', 'string', 'max:120'],
            'language' => ['sometimes', 'string', 'max:20'],
            'tradition' => ['sometimes', 'string', Rule::in(Tradition::values())],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }
}
