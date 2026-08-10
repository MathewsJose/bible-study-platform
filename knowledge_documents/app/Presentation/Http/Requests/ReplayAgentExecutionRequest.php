<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ReplayAgentExecutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'strict' => ['sometimes', 'boolean'],
            'dry_run' => ['sometimes', 'boolean'],
        ];
    }
}
