<?php

use App\Models\Client;
use App\Models\RecurringTask;
use App\Models\RecurringTaskLog;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->client = Client::factory()->create(['user_id' => $this->user->id]);
    Sanctum::actingAs($this->user);
});

describe('Recurring Task API - List', function () {
    it('lists recurring tasks for authenticated user', function () {
        RecurringTask::factory()->count(3)->create(['user_id' => $this->user->id]);
        RecurringTask::factory()->count(2)->create(); // Other user's tasks (different user_id via factory default)

        $response = $this->getJson('/api/v1/recurring-tasks');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');
    });

    it('filters by search term', function () {
        RecurringTask::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Website-Wartung',
        ]);
        RecurringTask::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Hosting-Verlängerung',
        ]);

        $response = $this->getJson('/api/v1/recurring-tasks?search=Wartung');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('filters by client_id', function () {
        RecurringTask::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);
        RecurringTask::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => null,
        ]);

        $response = $this->getJson("/api/v1/recurring-tasks?client_id={$this->client->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('filters by frequency', function () {
        RecurringTask::factory()->monthly()->create(['user_id' => $this->user->id]);
        RecurringTask::factory()->weekly()->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/v1/recurring-tasks?frequency=monthly');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.frequency', 'monthly');
    });

    it('filters by active status', function () {
        RecurringTask::factory()->create(['user_id' => $this->user->id, 'active' => true]);
        RecurringTask::factory()->inactive()->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/v1/recurring-tasks?active=true');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.active', true);
    });

    it('filters overdue tasks', function () {
        RecurringTask::factory()->overdue()->create(['user_id' => $this->user->id]);
        RecurringTask::factory()->create([
            'user_id' => $this->user->id,
            'next_due_at' => now()->addMonth(),
        ]);

        $response = $this->getJson('/api/v1/recurring-tasks?overdue=true');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_overdue', true);
    });

    it('paginates results', function () {
        RecurringTask::factory()->count(20)->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/v1/recurring-tasks?per_page=5');

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 20);
    });

    it('includes client relation', function () {
        RecurringTask::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->getJson('/api/v1/recurring-tasks');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['client' => ['id']]],
            ]);
    });
});

describe('Recurring Task API - Create', function () {
    it('creates a recurring task with valid data', function () {
        $data = [
            'title' => 'Monthly Server Backup',
            'description' => 'Run full server backup',
            'frequency' => 'monthly',
            'next_due_at' => now()->addMonth()->toDateString(),
            'client_id' => $this->client->id,
            'amount' => 150.00,
            'billing_notes' => 'Flat rate',
        ];

        $response = $this->postJson('/api/v1/recurring-tasks', $data);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Monthly Server Backup')
            ->assertJsonPath('data.frequency', 'monthly')
            ->assertJsonPath('data.frequency_label', 'Monatlich')
            ->assertJsonPath('data.active', true);

        $this->assertDatabaseHas('recurring_tasks', [
            'title' => 'Monthly Server Backup',
            'user_id' => $this->user->id,
        ]);
    });

    it('creates a task without a client', function () {
        $response = $this->postJson('/api/v1/recurring-tasks', [
            'title' => 'General Maintenance',
            'frequency' => 'weekly',
            'next_due_at' => now()->addWeek()->toDateString(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.client_id', null);
    });

    it('validates required fields', function () {
        $response = $this->postJson('/api/v1/recurring-tasks', []);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    });

    it('validates client belongs to user', function () {
        $otherClient = Client::factory()->create();

        $response = $this->postJson('/api/v1/recurring-tasks', [
            'title' => 'Test Task',
            'frequency' => 'monthly',
            'next_due_at' => now()->addMonth()->toDateString(),
            'client_id' => $otherClient->id,
        ]);

        $response->assertNotFound();
    });
});

describe('Recurring Task API - Show', function () {
    it('shows a task with computed fields and logs', function () {
        $task = RecurringTask::factory()->overdue()->create([
            'user_id' => $this->user->id,
        ]);

        RecurringTaskLog::factory()->count(3)->create([
            'recurring_task_id' => $task->id,
        ]);

        $response = $this->getJson("/api/v1/recurring-tasks/{$task->id}");

        $response->assertOk()
            ->assertJsonPath('data.is_overdue', true)
            ->assertJsonStructure([
                'data' => [
                    'frequency_label',
                    'frequency_color',
                    'is_overdue',
                    'is_due_soon',
                    'has_ended',
                    'logs',
                    'client',
                ],
            ]);
    });

    it('returns 404 for other users task', function () {
        $task = RecurringTask::factory()->create();

        $response = $this->getJson("/api/v1/recurring-tasks/{$task->id}");

        $response->assertNotFound();
    });
});

describe('Recurring Task API - Update', function () {
    it('updates a recurring task', function () {
        $task = RecurringTask::factory()->create(['user_id' => $this->user->id]);

        $response = $this->putJson("/api/v1/recurring-tasks/{$task->id}", [
            'title' => 'Updated Title',
            'frequency' => 'quarterly',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Updated Title')
            ->assertJsonPath('data.frequency', 'quarterly');
    });
});

describe('Recurring Task API - Delete', function () {
    it('deletes a recurring task', function () {
        $task = RecurringTask::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/v1/recurring-tasks/{$task->id}");

        $response->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('recurring_tasks', ['id' => $task->id]);
    });
});

describe('Recurring Task API - Pause', function () {
    it('pauses an active task', function () {
        $task = RecurringTask::factory()->create([
            'user_id' => $this->user->id,
            'active' => true,
        ]);

        $response = $this->postJson("/api/v1/recurring-tasks/{$task->id}/pause");

        $response->assertOk()
            ->assertJsonPath('data.active', false);
    });

    it('rejects pausing an already paused task', function () {
        $task = RecurringTask::factory()->inactive()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->postJson("/api/v1/recurring-tasks/{$task->id}/pause");

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'TASK_ALREADY_PAUSED');
    });
});

describe('Recurring Task API - Resume', function () {
    it('resumes a paused task', function () {
        $task = RecurringTask::factory()->inactive()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->postJson("/api/v1/recurring-tasks/{$task->id}/resume");

        $response->assertOk()
            ->assertJsonPath('data.active', true);
    });

    it('rejects resuming an already active task', function () {
        $task = RecurringTask::factory()->create([
            'user_id' => $this->user->id,
            'active' => true,
        ]);

        $response = $this->postJson("/api/v1/recurring-tasks/{$task->id}/resume");

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'TASK_ALREADY_ACTIVE');
    });
});

describe('Recurring Task API - Skip', function () {
    it('skips current occurrence', function () {
        $task = RecurringTask::factory()->monthly()->create([
            'user_id' => $this->user->id,
            'active' => true,
            'next_due_at' => now(),
        ]);

        $originalDueDate = $task->next_due_at->toDateString();

        $response = $this->postJson("/api/v1/recurring-tasks/{$task->id}/skip", [
            'reason' => 'Client on vacation',
        ]);

        $response->assertOk();

        // Verify the task was advanced
        $task->refresh();
        expect($task->next_due_at->toDateString())->not->toBe($originalDueDate);

        // Verify skip was logged
        $this->assertDatabaseHas('recurring_task_logs', [
            'recurring_task_id' => $task->id,
            'action' => 'skipped',
            'notes' => 'Client on vacation',
        ]);
    });

    it('rejects skipping an inactive task', function () {
        $task = RecurringTask::factory()->inactive()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->postJson("/api/v1/recurring-tasks/{$task->id}/skip");

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'TASK_NOT_ACTIVE');
    });
});

describe('Recurring Task API - Advance', function () {
    it('advances to next occurrence', function () {
        $task = RecurringTask::factory()->monthly()->create([
            'user_id' => $this->user->id,
            'active' => true,
            'next_due_at' => now(),
        ]);

        $originalDueDate = $task->next_due_at->toDateString();

        $response = $this->postJson("/api/v1/recurring-tasks/{$task->id}/advance");

        $response->assertOk();

        $task->refresh();
        expect($task->next_due_at->toDateString())->not->toBe($originalDueDate);
        expect($task->last_run_at)->not->toBeNull();
    });

    it('rejects advancing an inactive task', function () {
        $task = RecurringTask::factory()->inactive()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->postJson("/api/v1/recurring-tasks/{$task->id}/advance");

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'TASK_NOT_ACTIVE');
    });
});
