<?php

use App\Models\Client;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('API Authentication', function () {
    it('rejects requests without token', function () {
        $response = $this->getJson('/api/v1/clients');

        $response->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'AUTHENTICATION_REQUIRED')
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'suggestions',
                ],
            ]);
    });

    it('rejects requests with invalid token', function () {
        $response = $this->getJson('/api/v1/clients', [
            'Authorization' => 'Bearer invalid-token-12345',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('error.code', 'AUTHENTICATION_REQUIRED');
    });

    it('accepts requests with valid token', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/clients');

        $response->assertOk()
            ->assertJsonPath('success', true);
    });

    it('accepts requests with real token created via createToken', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->getJson('/api/v1/clients', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);
    });

    it('scopes data to authenticated user', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Client::factory()->count(3)->create(['user_id' => $user1->id]);
        Client::factory()->count(5)->create(['user_id' => $user2->id]);

        Sanctum::actingAs($user1);

        $response = $this->getJson('/api/v1/clients');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('prevents accessing other users resources', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $otherClient = Client::factory()->create(['user_id' => $user2->id]);

        Sanctum::actingAs($user1);

        $response = $this->getJson("/api/v1/clients/{$otherClient->id}");

        $response->assertNotFound();
    });
});

describe('API Rate Limiting', function () {
    it('applies rate limiting to API requests', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // The API is throttled at 60 requests per minute
        // We'll just verify the rate limit headers are present
        $response = $this->getJson('/api/v1/clients');

        $response->assertOk();
        expect($response->headers->has('X-RateLimit-Limit'))->toBeTrue();
        expect($response->headers->has('X-RateLimit-Remaining'))->toBeTrue();
    });
});

describe('API Error Responses', function () {
    it('returns validation errors in expected format', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/clients', [
            'type' => 'invalid',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'errors' => [
                        '*' => ['field', 'messages'],
                    ],
                ],
            ]);
    });

    it('returns not found errors in expected format', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/clients/999999');

        $response->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'NOT_FOUND')
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'suggestions',
                ],
            ]);
    });

    it('returns endpoint not found for invalid routes', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/nonexistent');

        $response->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'ENDPOINT_NOT_FOUND');
    });
});

describe('API Token Management', function () {
    it('can create API token', function () {
        $user = User::factory()->create();

        $token = $user->createToken('api-access');

        expect($token->plainTextToken)->not->toBeEmpty();
        expect($token->accessToken->name)->toBe('api-access');
    });

    it('can revoke API token', function () {
        $user = User::factory()->create();

        $token = $user->createToken('to-revoke');
        $tokenId = $token->accessToken->id;

        $user->tokens()->where('id', $tokenId)->delete();

        expect($user->tokens()->where('id', $tokenId)->exists())->toBeFalse();
    });

    it('can list user tokens', function () {
        $user = User::factory()->create();

        $user->createToken('token-1');
        $user->createToken('token-2');
        $user->createToken('token-3');

        expect($user->tokens()->count())->toBe(3);
    });

    it('updates last_used_at on token usage', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test-token');

        expect($token->accessToken->last_used_at)->toBeNull();

        $this->getJson('/api/v1/clients', [
            'Authorization' => 'Bearer '.$token->plainTextToken,
        ]);

        $token->accessToken->refresh();
        expect($token->accessToken->last_used_at)->not->toBeNull();
    });
});
