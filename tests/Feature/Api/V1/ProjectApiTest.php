<?php

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->client = Client::factory()->create(['user_id' => $this->user->id]);
    Sanctum::actingAs($this->user);
});

describe('Project API - List', function () {
    it('lists projects for authenticated user', function () {
        Project::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);
        Project::factory()->count(2)->create(); // Other user's projects

        $response = $this->getJson('/api/v1/projects');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');
    });

    it('filters projects by status', function () {
        Project::factory()->draft()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);
        Project::factory()->inProgress()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->getJson('/api/v1/projects?status=draft');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'draft');
    });

    it('includes client and items in response', function () {
        $project = Project::factory()
            ->hasItems(2)
            ->create([
                'user_id' => $this->user->id,
                'client_id' => $this->client->id,
            ]);

        $response = $this->getJson('/api/v1/projects');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'client' => ['id', 'company_name'],
                        'items' => [
                            '*' => ['id', 'description', 'quantity', 'unit_price'],
                        ],
                    ],
                ],
            ]);
    });
});

describe('Project API - Create', function () {
    it('creates a project with valid data', function () {
        $data = [
            'client_id' => $this->client->id,
            'title' => 'Website Redesign',
            'description' => 'Complete website overhaul',
            'type' => 'fixed',
            'fixed_price' => 5000.00,
            'offer_date' => now()->toDateString(),
            'offer_valid_until' => now()->addDays(30)->toDateString(),
        ];

        $response = $this->postJson('/api/v1/projects', $data);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Website Redesign')
            ->assertJsonPath('data.status', 'draft');
    });

    it('creates a project with items', function () {
        $data = [
            'client_id' => $this->client->id,
            'title' => 'Development Project',
            'type' => 'fixed',
            'fixed_price' => 3000.00,
            'items' => [
                [
                    'description' => 'Frontend Development',
                    'quantity' => 1,
                    'unit' => 'pauschal',
                    'unit_price' => 2000.00,
                ],
                [
                    'description' => 'Backend Development',
                    'quantity' => 1,
                    'unit' => 'pauschal',
                    'unit_price' => 1000.00,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/projects', $data);

        $response->assertCreated()
            ->assertJsonCount(2, 'data.items');
    });

    it('validates client belongs to user', function () {
        $otherClient = Client::factory()->create();

        $response = $this->postJson('/api/v1/projects', [
            'client_id' => $otherClient->id,
            'title' => 'Test',
            'type' => 'fixed',
            'fixed_price' => 1000,
        ]);

        $response->assertNotFound();
    });
});

describe('Project API - Show', function () {
    it('shows a specific project with computed fields', function () {
        $project = Project::factory()->inProgress()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->getJson("/api/v1/projects/{$project->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $project->id)
            ->assertJsonStructure([
                'data' => [
                    'allowed_transitions',
                    'can_be_invoiced',
                    'total_value',
                ],
            ]);
    });
});

describe('Project API - Update', function () {
    it('updates a project', function () {
        $project = Project::factory()->draft()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->putJson("/api/v1/projects/{$project->id}", [
            'title' => 'Updated Title',
            'fixed_price' => 6000.00,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Updated Title');

        expect((float) $response->json('data.fixed_price'))->toBe(6000.0);
    });

    it('updates project items', function () {
        $project = Project::factory()
            ->hasItems(1)
            ->create([
                'user_id' => $this->user->id,
                'client_id' => $this->client->id,
            ]);

        $existingItem = $project->items->first();

        $response = $this->putJson("/api/v1/projects/{$project->id}", [
            'items' => [
                [
                    'id' => $existingItem->id,
                    'description' => 'Updated Item',
                    'quantity' => 2,
                    'unit_price' => 500,
                ],
                [
                    'description' => 'New Item',
                    'quantity' => 1,
                    'unit_price' => 300,
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data.items');
    });
});

describe('Project API - Delete', function () {
    it('deletes a project without invoices', function () {
        $project = Project::factory()->draft()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->deleteJson("/api/v1/projects/{$project->id}");

        $response->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertSoftDeleted('projects', ['id' => $project->id]);
    });
});

describe('Project API - Transition', function () {
    it('transitions project status', function () {
        $project = Project::factory()->draft()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->postJson("/api/v1/projects/{$project->id}/transition", [
            'status' => 'sent',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'sent');

        expect($project->fresh()->offer_sent_at)->not->toBeNull();
    });

    it('rejects invalid transitions', function () {
        $project = Project::factory()->draft()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->postJson("/api/v1/projects/{$project->id}/transition", [
            'status' => 'completed',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_TRANSITION');
    });

    it('returns allowed transitions in response', function () {
        $project = Project::factory()->sent()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->getJson("/api/v1/projects/{$project->id}");

        $response->assertOk();
        expect($response->json('data.allowed_transitions'))
            ->toContain('accepted')
            ->toContain('declined')
            ->toContain('cancelled');
    });
});
