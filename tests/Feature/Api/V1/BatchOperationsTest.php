<?php

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

describe('Batch Operations API', function () {
    it('executes multiple operations in a transaction', function () {
        $response = $this->postJson('/api/v1/batch', [
            'operations' => [
                [
                    'action' => 'create',
                    'resource' => 'client',
                    'data' => [
                        'type' => 'company',
                        'company_name' => 'Batch Test GmbH',
                        'contact_name' => 'Test Contact',
                        'email' => 'batch@test.de',
                    ],
                ],
                [
                    'action' => 'create',
                    'resource' => 'reminder',
                    'data' => [
                        'title' => 'Follow up',
                        'due_at' => now()->addDays(7)->toIso8601String(),
                    ],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.succeeded', 2)
            ->assertJsonPath('data.failed', 0);

        $this->assertDatabaseHas('clients', ['company_name' => 'Batch Test GmbH']);
        $this->assertDatabaseHas('reminders', ['title' => 'Follow up']);
    });

    it('supports reference resolution between operations', function () {
        $response = $this->postJson('/api/v1/batch', [
            'operations' => [
                [
                    'action' => 'create',
                    'resource' => 'client',
                    'data' => [
                        '$ref' => 'new_client',
                        'type' => 'company',
                        'company_name' => 'Referenced Client',
                        'contact_name' => 'Test',
                        'email' => 'ref@test.de',
                    ],
                ],
                [
                    'action' => 'create',
                    'resource' => 'project',
                    'data' => [
                        'client_id' => '$ref:new_client',
                        'title' => 'Project for referenced client',
                        'type' => 'fixed',
                        'fixed_price' => 5000,
                    ],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.succeeded', 2);

        $client = Client::where('company_name', 'Referenced Client')->first();
        $this->assertDatabaseHas('projects', [
            'title' => 'Project for referenced client',
            'client_id' => $client->id,
        ]);
    });

    it('rolls back all operations on failure', function () {
        $response = $this->postJson('/api/v1/batch', [
            'operations' => [
                [
                    'action' => 'create',
                    'resource' => 'client',
                    'data' => [
                        'type' => 'company',
                        'company_name' => 'Should Be Rolled Back',
                        'contact_name' => 'Test',
                        'email' => 'rollback@test.de',
                    ],
                ],
                [
                    'action' => 'create',
                    'resource' => 'project',
                    'data' => [
                        'client_id' => 999999, // Non-existent client
                        'title' => 'This should fail',
                        'type' => 'fixed',
                    ],
                ],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'BATCH_FAILED');

        // First operation should be rolled back
        $this->assertDatabaseMissing('clients', ['company_name' => 'Should Be Rolled Back']);
    });

    it('handles project transitions', function () {
        $client = Client::factory()->create(['user_id' => $this->user->id]);
        $project = Project::factory()->draft()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
        ]);

        $response = $this->postJson('/api/v1/batch', [
            'operations' => [
                [
                    'action' => 'transition',
                    'resource' => 'project',
                    'id' => $project->id,
                    'data' => ['status' => 'sent'],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.results.0.data.status', 'sent');

        expect($project->fresh()->status->value)->toBe('sent');
    });

    it('handles invoice creation from project', function () {
        $client = Client::factory()->create(['user_id' => $this->user->id]);
        $project = Project::factory()->inProgress()->hasItems(1)->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
        ]);

        $response = $this->postJson('/api/v1/batch', [
            'operations' => [
                [
                    'action' => 'from_project',
                    'resource' => 'invoice',
                    'data' => ['project_id' => $project->id],
                ],
            ],
        ]);

        $response->assertOk();
        expect($response->json('data.results.0.data.number'))->toMatch('/^\d{4}-\d{3}$/');
    });

    it('limits batch size', function () {
        $operations = array_fill(0, 51, [
            'action' => 'create',
            'resource' => 'reminder',
            'data' => ['title' => 'Test', 'due_at' => now()->toIso8601String()],
        ]);

        $response = $this->postJson('/api/v1/batch', [
            'operations' => $operations,
        ]);

        $response->assertUnprocessable();
    });
});

describe('Validation Endpoint API', function () {
    it('validates operations without executing', function () {
        $response = $this->postJson('/api/v1/validate', [
            'operations' => [
                [
                    'action' => 'create',
                    'resource' => 'client',
                    'data' => [
                        'type' => 'company',
                        'company_name' => 'Test',
                        'contact_name' => 'Test Contact',
                        'email' => 'test@test.de',
                    ],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.validations.0.valid', true);

        // Should not create anything
        $this->assertDatabaseMissing('clients', ['company_name' => 'Test']);
    });

    it('returns validation errors for invalid operations', function () {
        $response = $this->postJson('/api/v1/validate', [
            'operations' => [
                [
                    'action' => 'create',
                    'resource' => 'client',
                    'data' => [
                        // Missing required fields
                    ],
                ],
                [
                    'action' => 'create',
                    'resource' => 'project',
                    'data' => [
                        // Missing client_id, title, type
                    ],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.validations.0.valid', false)
            ->assertJsonPath('data.validations.1.valid', false);

        expect($response->json('data.validations.0.errors'))->toContain('Client type is required.');
        expect($response->json('data.validations.1.errors'))->toContain('Client ID is required.');
    });

    it('validates unknown resources', function () {
        $response = $this->postJson('/api/v1/validate', [
            'operations' => [
                [
                    'action' => 'create',
                    'resource' => 'unknown',
                    'data' => [],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.valid', false);

        expect($response->json('data.validations.0.errors.0'))->toContain('Unknown resource');
    });

    it('validates unknown actions', function () {
        $response = $this->postJson('/api/v1/validate', [
            'operations' => [
                [
                    'action' => 'unknown_action',
                    'resource' => 'client',
                    'data' => [],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.valid', false);

        expect($response->json('data.validations.0.errors.0'))->toContain('Unknown action');
    });

    it('validates ID requirement for update/delete actions', function () {
        $response = $this->postJson('/api/v1/validate', [
            'operations' => [
                [
                    'action' => 'update',
                    'resource' => 'client',
                    'data' => ['company_name' => 'Updated'],
                    // Missing ID
                ],
                [
                    'action' => 'delete',
                    'resource' => 'project',
                    'data' => [],
                    // Missing ID
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.valid', false);

        expect($response->json('data.validations.0.errors'))->toContain('ID is required for update action.');
        expect($response->json('data.validations.1.errors'))->toContain('ID is required for delete action.');
    });
});
