<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->client = Client::factory()->create(['user_id' => $this->user->id]);
    $this->project = Project::factory()->hourly()->inProgress()->create([
        'user_id' => $this->user->id,
        'client_id' => $this->client->id,
    ]);
    Sanctum::actingAs($this->user);
});

describe('Time Entry API - List', function () {
    it('lists time entries for authenticated user', function () {
        TimeEntry::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
        ]);
        TimeEntry::factory()->count(2)->create(); // Other user's entries

        $response = $this->getJson('/api/v1/time-entries');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');
    });

    it('filters by project_id', function () {
        $otherProject = Project::factory()->hourly()->inProgress()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
        ]);
        TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $otherProject->id,
        ]);

        $response = $this->getJson("/api/v1/time-entries?project_id={$this->project->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('filters by billable', function () {
        TimeEntry::factory()->billable()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
        ]);
        TimeEntry::factory()->nonBillable()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
        ]);

        $response = $this->getJson('/api/v1/time-entries?billable=true');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.billable', true);
    });

    it('filters by invoiced status', function () {
        TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
        ]);
        $invoice = Invoice::factory()->create(['user_id' => $this->user->id]);
        TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'invoice_id' => $invoice->id,
        ]);

        $response = $this->getJson('/api/v1/time-entries?invoiced=false');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_invoiced', false);
    });

    it('filters by search term', function () {
        TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'description' => 'Working on homepage design',
        ]);
        TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'description' => 'Database migration',
        ]);

        $response = $this->getJson('/api/v1/time-entries?search=homepage');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('paginates results', function () {
        TimeEntry::factory()->count(20)->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
        ]);

        $response = $this->getJson('/api/v1/time-entries?per_page=5');

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 20);
    });

    it('includes project relation', function () {
        TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
        ]);

        $response = $this->getJson('/api/v1/time-entries');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['project' => ['id', 'title']]],
            ]);
    });
});

describe('Time Entry API - Create', function () {
    it('creates a time entry with valid data', function () {
        $data = [
            'project_id' => $this->project->id,
            'description' => 'Working on API endpoints',
            'started_at' => now()->subHours(2)->toIso8601String(),
            'ended_at' => now()->toIso8601String(),
            'billable' => true,
        ];

        $response = $this->postJson('/api/v1/time-entries', $data);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.description', 'Working on API endpoints')
            ->assertJsonPath('data.billable', true)
            ->assertJsonPath('data.is_invoiced', false);

        $this->assertDatabaseHas('time_entries', [
            'description' => 'Working on API endpoints',
            'user_id' => $this->user->id,
        ]);
    });

    it('rejects time entry for non-hourly project', function () {
        $fixedProject = Project::factory()->fixed()->inProgress()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->postJson('/api/v1/time-entries', [
            'project_id' => $fixedProject->id,
            'started_at' => now()->toIso8601String(),
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'PROJECT_NOT_HOURLY');
    });

    it('rejects time entry for other users project', function () {
        $otherProject = Project::factory()->hourly()->inProgress()->create();

        $response = $this->postJson('/api/v1/time-entries', [
            'project_id' => $otherProject->id,
            'started_at' => now()->toIso8601String(),
        ]);

        $response->assertNotFound();
    });

    it('validates required fields', function () {
        $response = $this->postJson('/api/v1/time-entries', []);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    });
});

describe('Time Entry API - Show', function () {
    it('shows a time entry with computed fields', function () {
        $entry = TimeEntry::factory()->withDuration(90)->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
        ]);

        $response = $this->getJson("/api/v1/time-entries/{$entry->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $entry->id)
            ->assertJsonStructure([
                'data' => [
                    'duration_minutes',
                    'duration_hours',
                    'formatted_duration',
                    'is_running',
                    'is_invoiced',
                    'project',
                ],
            ]);
    });

    it('returns 404 for other users entry', function () {
        $entry = TimeEntry::factory()->create();

        $response = $this->getJson("/api/v1/time-entries/{$entry->id}");

        $response->assertNotFound();
    });
});

describe('Time Entry API - Update', function () {
    it('updates a time entry', function () {
        $entry = TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
        ]);

        $response = $this->putJson("/api/v1/time-entries/{$entry->id}", [
            'description' => 'Updated description',
            'billable' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.description', 'Updated description')
            ->assertJsonPath('data.billable', false);
    });

    it('rejects updating an invoiced time entry', function () {
        $invoice = Invoice::factory()->create(['user_id' => $this->user->id]);
        $entry = TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'invoice_id' => $invoice->id,
        ]);

        $response = $this->putJson("/api/v1/time-entries/{$entry->id}", [
            'description' => 'Updated',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'TIME_ENTRY_INVOICED');
    });
});

describe('Time Entry API - Delete', function () {
    it('deletes a time entry', function () {
        $entry = TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
        ]);

        $response = $this->deleteJson("/api/v1/time-entries/{$entry->id}");

        $response->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('time_entries', ['id' => $entry->id]);
    });

    it('rejects deleting an invoiced time entry', function () {
        $invoice = Invoice::factory()->create(['user_id' => $this->user->id]);
        $entry = TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'invoice_id' => $invoice->id,
        ]);

        $response = $this->deleteJson("/api/v1/time-entries/{$entry->id}");

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'TIME_ENTRY_INVOICED');
    });
});

describe('Time Entry API - Timer', function () {
    it('starts a timer', function () {
        $response = $this->postJson('/api/v1/time-entries/start', [
            'project_id' => $this->project->id,
            'description' => 'Working on feature',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.is_running', true)
            ->assertJsonPath('data.description', 'Working on feature');
    });

    it('prevents starting duplicate timer', function () {
        TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'started_at' => now(),
            'ended_at' => null,
            'duration_minutes' => null,
        ]);

        $response = $this->postJson('/api/v1/time-entries/start', [
            'project_id' => $this->project->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'TIMER_ALREADY_RUNNING');
    });

    it('stops a running timer', function () {
        $entry = TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'started_at' => now()->subHour(),
            'ended_at' => null,
            'duration_minutes' => null,
        ]);

        $response = $this->postJson("/api/v1/time-entries/{$entry->id}/stop");

        $response->assertOk()
            ->assertJsonPath('data.is_running', false);

        expect($entry->fresh()->ended_at)->not->toBeNull();
        expect($entry->fresh()->duration_minutes)->toBeGreaterThan(0);
    });

    it('rejects stopping an already stopped timer', function () {
        $entry = TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
        ]);

        $response = $this->postJson("/api/v1/time-entries/{$entry->id}/stop");

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'TIMER_NOT_RUNNING');
    });
});
