<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AiAnswerFeedback;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

final class AiFeedbackHealthCommand extends Command
{
    protected $signature = 'ai:feedback:health {--days=30 : Number of recent days to include} {--format=table : table or json}';

    protected $description = 'Show safe aggregate AI answer feedback health.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));

        if (! Schema::hasTable('ai_answer_feedback')) {
            $payload = [
                'status' => 'unavailable',
                'message' => 'Feedback table is missing. Run php artisan migrate before checking AI feedback health.',
                'days' => $days,
            ];

            if ($this->option('format') === 'json') {
                $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

                return self::FAILURE;
            }

            $this->error($payload['message']);

            return self::FAILURE;
        }

        $query = AiAnswerFeedback::query()->where('created_at', '>=', now()->subDays($days));
        $total = (clone $query)->count();
        $helpful = (clone $query)->where('rating', 'helpful')->count();
        $notHelpful = (clone $query)->where('rating', 'not_helpful')->count();
        $topReasons = (clone $query)
            ->where('rating', 'not_helpful')
            ->whereNotNull('reason')
            ->selectRaw('reason, count(*) as total')
            ->groupBy('reason')
            ->orderByDesc('total')
            ->limit(5)
            ->pluck('total', 'reason')
            ->all();

        $payload = [
            'days' => $days,
            'total' => $total,
            'helpful' => $helpful,
            'not_helpful' => $notHelpful,
            'helpful_rate' => $total === 0 ? 0.0 : round($helpful / $total, 4),
            'top_negative_reasons' => $topReasons,
        ];

        if ($this->option('format') === 'json') {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->info('AI Feedback Health');
        $this->table(['Metric', 'Value'], [
            ['Days', $payload['days']],
            ['Total feedback', $payload['total']],
            ['Helpful', $payload['helpful']],
            ['Not helpful', $payload['not_helpful']],
            ['Helpful rate', $payload['helpful_rate']],
        ]);

        if ($topReasons !== []) {
            $this->table(['Negative reason', 'Count'], array_map(
                static fn (string $reason, int $count): array => [$reason, $count],
                array_keys($topReasons),
                array_values($topReasons),
            ));
        }

        return self::SUCCESS;
    }
}
