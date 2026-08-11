<?php

declare(strict_types=1);

use App\Infrastructure\Knowledge\Persistence\AiEvaluationResultRecord;
use App\Infrastructure\Knowledge\Persistence\AiEvaluationRunRecord;
use App\Infrastructure\Knowledge\Persistence\EvaluationQuestionRecord;
use Database\Seeders\EvaluationQuestionSeeder;
use Illuminate\Support\Facades\Artisan;

it('seeds a production sized evaluation dataset with difficulty and metadata', function (): void {
    $this->seed(EvaluationQuestionSeeder::class);

    $question = EvaluationQuestionRecord::query()
        ->where('question', 'Why did Jesus become man?')
        ->firstOrFail();

    expect(EvaluationQuestionRecord::query()->count())->toBeGreaterThanOrEqual(50)
        ->and(EvaluationQuestionRecord::query()->count())->toBeLessThanOrEqual(100)
        ->and(EvaluationQuestionRecord::query()->whereNotNull('difficulty')->count())->toBe(EvaluationQuestionRecord::query()->count())
        ->and($question->expected_answer_facts)->toBeArray()
        ->and($question->required_citations)->toBeArray()
        ->and($question->metadata)->toMatchArray([
            'requires_multiple_sources' => true,
        ])
        ->and(EvaluationQuestionRecord::query()->where('coverage_status', 'unavailable')->count())->toBeGreaterThan(0);
});

it('runs and persists deterministic safety evaluations with fingerprints', function (): void {
    config()->set('ai_security.enabled', true);
    config()->set('ai_security.pii.action', 'redact');
    config()->set('ai_security.prompt_injection.action', 'block');
    config()->set('ai_security.prompt_injection.threshold', 2);
    config()->set('ai_security.limits.max_input_characters', 1000);

    $this->artisan('ai:evaluate', [
        '--type' => 'safety',
        '--save' => true,
        '--name' => 'safety-smoke',
    ])
        ->expectsOutputToContain('AI Evaluation')
        ->expectsOutputToContain('Status: passed')
        ->assertSuccessful();

    $run = AiEvaluationRunRecord::query()->firstOrFail();

    expect($run->name)->toBe('safety-smoke')
        ->and($run->evaluation_type)->toBe('safety')
        ->and($run->status)->toBe('passed')
        ->and($run->fingerprints)->toHaveKeys(['hash', 'execution_hash', 'corpus', 'security_hash', 'payload'])
        ->and(AiEvaluationResultRecord::query()->count())->toBe(3);
});

it('returns json output for ai evaluation commands', function (): void {
    config()->set('ai_security.limits.max_input_characters', 1000);

    $status = Artisan::call('ai:evaluate', [
        '--type' => 'safety',
        '--format' => 'json',
    ]);
    $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($status)->toBe(0)
        ->and($payload['evaluation_type'])->toBe('safety')
        ->and($payload['status'])->toBe('passed')
        ->and($payload['results'])->toHaveCount(3);
});

it('compares saved evaluation runs and reports threshold regressions', function (): void {
    config()->set('evaluation.thresholds.maximum_score_drop', 0.05);
    config()->set('evaluation.thresholds.maximum_failure_rate', 0.5);
    config()->set('evaluation.thresholds.minimum_average_score', 0.5);

    $baseline = AiEvaluationRunRecord::query()->create([
        'name' => 'baseline',
        'evaluation_type' => 'safety',
        'status' => 'passed',
        'total_questions' => 1,
        'metrics' => ['total' => 1, 'failed' => 0, 'average_score' => 0.90, 'average_latency_ms' => 20],
        'configuration' => [],
        'fingerprints' => [],
        'thresholds' => [],
        'metadata' => [],
    ]);
    $current = AiEvaluationRunRecord::query()->create([
        'name' => 'current',
        'evaluation_type' => 'safety',
        'status' => 'failed',
        'total_questions' => 1,
        'metrics' => ['total' => 1, 'failed' => 1, 'average_score' => 0.70, 'average_latency_ms' => 25],
        'configuration' => [],
        'fingerprints' => [],
        'thresholds' => [],
        'metadata' => [],
    ]);

    AiEvaluationResultRecord::query()->create([
        'ai_evaluation_run_id' => $baseline->id,
        'evaluation_type' => 'safety',
        'category' => 'prompt_injection',
        'status' => 'pass',
        'score' => 0.90,
        'metrics' => [],
        'expected' => [],
        'actual' => [],
        'warnings' => [],
        'latency_ms' => 20,
    ]);
    AiEvaluationResultRecord::query()->create([
        'ai_evaluation_run_id' => $current->id,
        'evaluation_type' => 'safety',
        'category' => 'prompt_injection',
        'status' => 'fail',
        'score' => 0.70,
        'metrics' => [],
        'expected' => [],
        'actual' => [],
        'warnings' => [],
        'latency_ms' => 25,
    ]);

    $status = Artisan::call('ai:evaluate:compare', [
        '--baseline' => $baseline->id,
        '--current' => $current->id,
        '--format' => 'json',
    ]);
    $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($status)->toBe(1)
        ->and($payload['status'])->toBe('FAIL')
        ->and($payload['metric_deltas']['average_score']['delta'])->toBe(-0.2)
        ->and($payload['regressed_questions'])->toBe(['prompt_injection']);
});
