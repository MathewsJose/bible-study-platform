<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_sanctum_token(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('test-token');

        $this->assertNotEmpty($token->plainTextToken);
        $this->assertStringContainsString('test-token', $token->accessToken->name);
        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'test-token',
            'tokenable_type' => User::class,
            'tokenable_id' => (string) $user->getKey(),
        ]);
    }

    public function test_api_user_endpoint_requires_sanctum_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token->plainTextToken,
        ])->getJson('/user');

        $response->assertStatus(200)
            ->assertJsonPath('id', $user->getKey())
            ->assertJsonPath('email', $user->email);
    }
}
