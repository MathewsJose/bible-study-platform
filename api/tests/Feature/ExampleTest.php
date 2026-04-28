<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_validation_response(): void
    {
        $response = $this->getJson('/bible?book=john');

        $response
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid bible chapter request.');
    }
}
