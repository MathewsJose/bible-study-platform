<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AiAnswerFeedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class AiAnswerFeedbackApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_feedback_endpoint_requires_authentication(): void
    {
        $this->postJson('/v1/knowledge/answers/feedback', [
            'request_id' => 'alpha-request-1',
            'rating' => 'helpful',
        ])->assertUnauthorized();
    }

    public function test_authenticated_user_can_submit_helpful_feedback(): void
    {
        $token = User::factory()->create()->createToken('feedback-test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/v1/knowledge/answers/feedback', [
                'request_id' => 'alpha-request-1',
                'rating' => 'helpful',
                'provider' => 'null',
                'model' => 'null-answer-model',
                'source_count' => 2,
                'citation_count' => 1,
                'client_surface' => 'alpha_question',
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.rating', 'helpful');

        $record = AiAnswerFeedback::query()->firstOrFail();

        $this->assertEquals('alpha-request-1', $record->request_id);
        $this->assertEquals('null', $record->provider);
        $this->assertEquals(2, $record->source_count);
        $this->assertEquals('alpha_question', $record->metadata['client_surface']);
    }

    public function test_negative_feedback_captures_reason_without_storing_comment_by_default(): void
    {
        $token = User::factory()->create()->createToken('feedback-test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/v1/knowledge/answers/feedback', [
                'request_id' => 'alpha-request-2',
                'rating' => 'not_helpful',
                'reason' => 'incorrect_citation',
                'comment' => 'My email is user@example.com and this citation was wrong.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.comment_stored', false);

        $record = AiAnswerFeedback::query()->firstOrFail();

        $this->assertEquals('incorrect_citation', $record->reason);
        $this->assertNull($record->comment);
    }

    public function test_duplicate_feedback_for_same_user_and_request_updates_existing_record(): void
    {
        $token = User::factory()->create()->createToken('feedback-test')->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token];

        $this->withHeaders($headers)
            ->postJson('/v1/knowledge/answers/feedback', [
                'request_id' => 'alpha-request-3',
                'rating' => 'helpful',
            ])
            ->assertCreated();

        $this->withHeaders($headers)
            ->postJson('/v1/knowledge/answers/feedback', [
                'request_id' => 'alpha-request-3',
                'rating' => 'not_helpful',
                'reason' => 'missing_information',
            ])
            ->assertOk()
            ->assertJsonPath('data.rating', 'not_helpful');

        $this->assertSame(1, AiAnswerFeedback::query()->count());
        $this->assertEquals('missing_information', AiAnswerFeedback::query()->firstOrFail()->reason);
    }

    public function test_feedback_validation_uses_public_error_contract(): void
    {
        $token = User::factory()->create()->createToken('feedback-test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/v1/knowledge/answers/feedback', [
                'request_id' => '',
                'rating' => 'maybe',
            ])
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid AI answer feedback request.');
    }

    public function test_feedback_endpoint_is_rate_limited_separately(): void
    {
        config()->set('knowledge_service.feedback_rate_limit_per_minute', 1);

        $token = User::factory()->create()->createToken('feedback-test')->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token];

        $this->withHeaders($headers)
            ->postJson('/v1/knowledge/answers/feedback', [
                'request_id' => 'alpha-request-rate-1',
                'rating' => 'helpful',
            ])
            ->assertCreated();

        $this->withHeaders($headers)
            ->postJson('/v1/knowledge/answers/feedback', [
                'request_id' => 'alpha-request-rate-2',
                'rating' => 'helpful',
            ])
            ->assertStatus(429);
    }

    public function test_feedback_health_command_reports_safe_aggregates(): void
    {
        $user = User::factory()->create();
        AiAnswerFeedback::query()->create([
            'user_id' => $user->id,
            'request_id' => 'alpha-request-health-1',
            'rating' => 'helpful',
        ]);
        AiAnswerFeedback::query()->create([
            'user_id' => $user->id,
            'request_id' => 'alpha-request-health-2',
            'rating' => 'not_helpful',
            'reason' => 'incorrect_answer',
            'comment' => 'private comment',
        ]);

        $status = Artisan::call('ai:feedback:health', ['--format' => 'json']);
        $payload = Artisan::output();

        $this->assertSame(0, $status);
        $this->assertStringContainsString('"total": 2', $payload);
        $this->assertStringContainsString('"incorrect_answer": 1', $payload);
        $this->assertStringNotContainsString('private comment', $payload);
    }
}
