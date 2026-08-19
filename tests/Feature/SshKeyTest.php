<?php

namespace Tests\Feature;

use Tests\TestCase;

class SshKeyTest extends TestCase
{
    public function test_get_public_key_returns_key(): void
    {
        $response = $this->getJson('/api/ssh/public-key');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'public_key',
            'path',
        ]);
        $this->assertNotEmpty($response->json('public_key'));
    }

    public function test_test_connection_validation(): void
    {
        $response = $this->postJson('/api/ssh/test', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['node']);
    }

    public function test_test_all_validation(): void
    {
        $response = $this->postJson('/api/ssh/test-all', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['nodes']);
    }

    public function test_authorize_validation(): void
    {
        $response = $this->postJson('/api/ssh/authorize', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['node', 'password']);
    }

    public function test_test_connection_returns_expected_structure(): void
    {
        // Testing localhost or unreachable host
        $response = $this->postJson('/api/ssh/test', [
            'node' => '127.0.0.1',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'node',
            'connected',
            'status',
            'message',
        ]);
    }
}
