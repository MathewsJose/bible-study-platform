<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests;

use App\Domain\Knowledge\Enums\SourceType;
use App\Domain\Knowledge\Enums\Tradition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListKnowledgeDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'source_type' => ['sometimes', 'string', Rule::in(SourceType::values())],
            'tradition' => ['sometimes', 'string', Rule::in(Tradition::values())],
            'reference' => ['sometimes', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
