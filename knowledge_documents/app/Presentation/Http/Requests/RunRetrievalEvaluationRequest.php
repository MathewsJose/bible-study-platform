<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RunRetrievalEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'top_k' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'minimum_score' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'strategy' => ['sometimes', 'string', 'in:vector,lexical,hybrid'],
            'question_id' => ['sometimes', 'uuid'],
            'category' => ['sometimes', 'string', 'max:80'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'save' => ['sometimes', 'boolean'],
        ];
    }
}
