<?php

use App\Models\Client;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

describe('Client API - List', function () {
    it('lists clients for authenticated user', function () {
        Client::factory()->count(3)->create(['user_id' => $this->user->id]);
        Client::factory()->count(2)->create(); // Other user's clients

        $response = $this->getJson('/api/v1/clients');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');
    });

    it('filters clients by search term', function () {
        Client::factory()->create([
            'user_id' => $this->user->id,
            'company_name' => 'Acme Corporation',
        ]);
        Client::factory()->create([
            'user_id' => $this->user->id,
            'company_name' => 'Other Company',
        ]);

        $response = $this->getJson('/api/v1/clients?search=Acme');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.company_name', 'Acme Corporation');
    });

    it('filters clients by type', function () {
        Client::factory()->company()->create(['user_id' => $this->user->id]);
        Client::factory()->individual()->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/v1/clients?type=company');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'company');
    });

    it('paginates results', function () {
        Client::factory()->count(20)->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/v1/clients?per_page=5');

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 20);
    });
});

describe('Client API - Create', function () {
    it('creates a client with valid data', function () {
        $data = [
            'type' => 'company',
            'company_name' => 'Test Company GmbH',
            'contact_name' => 'Max Mustermann',
            'email' => 'max@testcompany.de',
            'phone' => '+49 123 456789',
            'street' => 'Musterstraße 1',
            'postal_code' => '12345',
            'city' => 'Berlin',
            'country' => 'DE',
        ];

        $response = $this->postJson('/api/v1/clients', $data);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.company_name', 'Test Company GmbH')
            ->assertJsonPath('data.email', 'max@testcompany.de');

        $this->assertDatabaseHas('clients', [
            'company_name' => 'Test Company GmbH',
            'user_id' => $this->user->id,
        ]);
    });

    it('validates required fields', function () {
        $response = $this->postJson('/api/v1/clients', []);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    });

    it('validates client type enum', function () {
        $response = $this->postJson('/api/v1/clients', [
            'type' => 'invalid',
            'contact_name' => 'Test',
            'email' => 'test@test.de',
        ]);

        $response->assertUnprocessable();
    });
});

describe('Client API - Show', function () {
    it('shows a specific client', function () {
        $client = Client::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson("/api/v1/clients/{$client->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $client->id);
    });

    it('returns 404 for non-existent client', function () {
        $response = $this->getJson('/api/v1/clients/99999');

        $response->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    });

    it('returns 404 for another user\'s client', function () {
        $otherClient = Client::factory()->create();

        $response = $this->getJson("/api/v1/clients/{$otherClient->id}");

        $response->assertNotFound();
    });
});

describe('Client API - Update', function () {
    it('updates a client', function () {
        $client = Client::factory()->create(['user_id' => $this->user->id]);

        $response = $this->putJson("/api/v1/clients/{$client->id}", [
            'company_name' => 'Updated Company',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.company_name', 'Updated Company');

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'company_name' => 'Updated Company',
        ]);
    });

    it('cannot update another user\'s client', function () {
        $otherClient = Client::factory()->create();

        $response = $this->putJson("/api/v1/clients/{$otherClient->id}", [
            'company_name' => 'Hacked',
        ]);

        $response->assertNotFound();
    });
});

describe('Client API - Delete', function () {
    it('deletes a client without relations', function () {
        $client = Client::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/v1/clients/{$client->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.deleted', true);

        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    });

    it('prevents deleting client with projects', function () {
        $client = Client::factory()
            ->hasProjects(1, ['user_id' => $this->user->id])
            ->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/v1/clients/{$client->id}");

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'CLIENT_HAS_RELATIONS');
    });
});
