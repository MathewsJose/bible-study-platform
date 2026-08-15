<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AiAnswerFeedback extends Model
{
    use HasFactory;

    protected $table = 'ai_answer_feedback';

    protected $fillable = [
        'user_id',
        'request_id',
        'answer_execution_id',
        'rating',
        'reason',
        'comment',
        'provider',
        'model',
        'retrieval_strategy',
        'source_count',
        'citation_count',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'source_count' => 'integer',
            'citation_count' => 'integer',
        ];
    }

    /** @return BelongsTo<User, AiAnswerFeedback> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
