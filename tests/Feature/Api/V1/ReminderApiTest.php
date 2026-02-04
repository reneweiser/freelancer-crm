<?php

use App\Enums\ReminderRecurrence;
use App\Models\Client;
use App\Models\Reminder;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->client = Client::factory()->create(['user_id' => $this->user->id]);
    Sanctum::actingAs($this->user);
});

describe('Reminder API - List', function () {
    it('lists reminders for authenticated user', function () {
        Reminder::factory()->count(3)->create(['user_id' => $this->user->id]);
        Reminder::factory()->count(2)->create(); // Other user's reminders

        $response = $this->getJson('/api/v1/reminders');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');
    });

    it('filters by status pending', function () {
        Reminder::factory()->create(['user_id' => $this->user->id]);
        Reminder::factory()->completed()->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/v1/reminders?status=pending');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('filters by priority', function () {
        Reminder::factory()->highPriority()->create(['user_id' => $this->user->id]);
        Reminder::factory()->lowPriority()->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/v1/reminders?priority=high');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.priority', 'high');
    });

    it('filters by remindable type', function () {
        Reminder::factory()->create([
            'user_id' => $this->user->id,
            'remindable_type' => Client::class,
            'remindable_id' => $this->client->id,
        ]);
        Reminder::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/v1/reminders?remindable_type=Client');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.remindable_type', 'Client');
    });

    it('filters upcoming reminders', function () {
        Reminder::factory()->create([
            'user_id' => $this->user->id,
            'due_at' => now()->addDays(3),
        ]);
        Reminder::factory()->create([
            'user_id' => $this->user->id,
            'due_at' => now()->addDays(10),
        ]);

        $response = $this->getJson('/api/v1/reminders?upcoming_days=7');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });
});

describe('Reminder API - Create', function () {
    it('creates a basic reminder', function () {
        $response = $this->postJson('/api/v1/reminders', [
            'title' => 'Follow up with client',
            'due_at' => now()->addDays(7)->toIso8601String(),
            'priority' => 'high',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Follow up with client')
            ->assertJsonPath('data.priority', 'high');
    });

    it('creates a reminder attached to a client', function () {
        $response = $this->postJson('/api/v1/reminders', [
            'title' => 'Client reminder',
            'due_at' => now()->addDays(3)->toIso8601String(),
            'remindable_type' => 'Client',
            'remindable_id' => $this->client->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.remindable_type', 'Client')
            ->assertJsonPath('data.remindable_id', $this->client->id);
    });

    it('creates a recurring reminder', function () {
        $response = $this->postJson('/api/v1/reminders', [
            'title' => 'Weekly check-in',
            'due_at' => now()->addDays(7)->toIso8601String(),
            'recurrence' => 'weekly',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.recurrence', 'weekly')
            ->assertJsonPath('data.recurrence_label', 'Wöchentlich');
    });

    it('validates remindable belongs to user', function () {
        $otherClient = Client::factory()->create();

        $response = $this->postJson('/api/v1/reminders', [
            'title' => 'Test',
            'due_at' => now()->addDays(1)->toIso8601String(),
            'remindable_type' => 'Client',
            'remindable_id' => $otherClient->id,
        ]);

        $response->assertNotFound()
            ->assertJsonPath('error.code', 'REMINDABLE_NOT_FOUND');
    });
});

describe('Reminder API - Show', function () {
    it('shows a reminder with computed fields', function () {
        $reminder = Reminder::factory()->overdue()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/reminders/{$reminder->id}");

        $response->assertOk()
            ->assertJsonPath('data.is_overdue', true)
            ->assertJsonStructure([
                'data' => [
                    'effective_due_at',
                    'priority_label',
                    'priority_color',
                ],
            ]);
    });
});

describe('Reminder API - Update', function () {
    it('updates a reminder', function () {
        $reminder = Reminder::factory()->create(['user_id' => $this->user->id]);

        $response = $this->putJson("/api/v1/reminders/{$reminder->id}", [
            'title' => 'Updated title',
            'priority' => 'high',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Updated title')
            ->assertJsonPath('data.priority', 'high');
    });

    it('rejects updating system reminders', function () {
        $reminder = Reminder::factory()->system()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->putJson("/api/v1/reminders/{$reminder->id}", [
            'title' => 'Updated',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'SYSTEM_REMINDER');
    });
});

describe('Reminder API - Delete', function () {
    it('deletes a reminder', function () {
        $reminder = Reminder::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/v1/reminders/{$reminder->id}");

        $response->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('reminders', ['id' => $reminder->id]);
    });
});

describe('Reminder API - Complete', function () {
    it('completes a reminder', function () {
        $reminder = Reminder::factory()->create(['user_id' => $this->user->id]);

        $response = $this->postJson("/api/v1/reminders/{$reminder->id}/complete");

        $response->assertOk();
        expect($reminder->fresh()->completed_at)->not->toBeNull();
    });

    it('creates next occurrence for recurring reminder', function () {
        $reminder = Reminder::factory()
            ->recurring(ReminderRecurrence::Weekly)
            ->create([
                'user_id' => $this->user->id,
                'due_at' => now(),
            ]);

        $response = $this->postJson("/api/v1/reminders/{$reminder->id}/complete");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'completed',
                    'next_occurrence',
                ],
            ]);

        expect($response->json('data.next_occurrence'))->not->toBeNull();
    });

    it('rejects completing already completed reminder', function () {
        $reminder = Reminder::factory()->completed()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->postJson("/api/v1/reminders/{$reminder->id}/complete");

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'ALREADY_COMPLETED');
    });
});

describe('Reminder API - Snooze', function () {
    it('snoozes a reminder', function () {
        $reminder = Reminder::factory()->create(['user_id' => $this->user->id]);

        $response = $this->postJson("/api/v1/reminders/{$reminder->id}/snooze", [
            'hours' => 48,
        ]);

        $response->assertOk();
        expect($reminder->fresh()->snoozed_until)->not->toBeNull();
    });

    it('uses default 24 hours if not specified', function () {
        $reminder = Reminder::factory()->create(['user_id' => $this->user->id]);

        $response = $this->postJson("/api/v1/reminders/{$reminder->id}/snooze");

        $response->assertOk();
        $snoozedUntil = $reminder->fresh()->snoozed_until;
        expect($snoozedUntil->diffInHours(now(), true))->toBeLessThan(25);
    });

    it('rejects snoozing completed reminder', function () {
        $reminder = Reminder::factory()->completed()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->postJson("/api/v1/reminders/{$reminder->id}/snooze");

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'ALREADY_COMPLETED');
    });
});
