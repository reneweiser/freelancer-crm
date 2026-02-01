<?php

use App\Enums\ReminderPriority;
use App\Enums\ReminderRecurrence;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Reminder;
use App\Models\User;
use App\Services\ReminderService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->client = Client::factory()->create(['user_id' => $this->user->id]);
    $this->actingAs($this->user);
});

describe('Reminder Model', function () {
    it('can create a basic reminder', function () {
        $reminder = Reminder::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Test Reminder',
            'due_at' => now()->addDays(3),
        ]);

        expect($reminder->title)->toBe('Test Reminder')
            ->and($reminder->user_id)->toBe($this->user->id)
            ->and($reminder->completed_at)->toBeNull();
    });

    it('can attach reminder to a client', function () {
        $reminder = Reminder::factory()->create([
            'user_id' => $this->user->id,
            'remindable_type' => Client::class,
            'remindable_id' => $this->client->id,
        ]);

        expect($reminder->remindable)->toBeInstanceOf(Client::class)
            ->and($reminder->remindable->id)->toBe($this->client->id);
    });

    it('can attach reminder to a project', function () {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $reminder = Reminder::factory()->create([
            'user_id' => $this->user->id,
            'remindable_type' => Project::class,
            'remindable_id' => $project->id,
        ]);

        expect($reminder->remindable)->toBeInstanceOf(Project::class)
            ->and($reminder->remindable->id)->toBe($project->id);
    });

    it('can attach reminder to an invoice', function () {
        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $reminder = Reminder::factory()->create([
            'user_id' => $this->user->id,
            'remindable_type' => Invoice::class,
            'remindable_id' => $invoice->id,
        ]);

        expect($reminder->remindable)->toBeInstanceOf(Invoice::class)
            ->and($reminder->remindable->id)->toBe($invoice->id);
    });

    it('detects overdue reminders', function () {
        $overdueReminder = Reminder::factory()->overdue()->create([
            'user_id' => $this->user->id,
        ]);

        expect($overdueReminder->is_overdue)->toBeTrue();
    });

    it('detects non-overdue reminders', function () {
        $futureReminder = Reminder::factory()->create([
            'user_id' => $this->user->id,
            'due_at' => now()->addDays(3),
        ]);

        expect($futureReminder->is_overdue)->toBeFalse();
    });

    it('detects reminders due today', function () {
        $todayReminder = Reminder::factory()->dueToday()->create([
            'user_id' => $this->user->id,
        ]);

        expect($todayReminder->is_due_today)->toBeTrue();
    });
});

describe('Reminder Scopes', function () {
    it('filters pending reminders', function () {
        Reminder::factory()->create(['user_id' => $this->user->id]);
        Reminder::factory()->completed()->create(['user_id' => $this->user->id]);

        expect(Reminder::pending()->count())->toBe(1);
    });

    it('filters completed reminders', function () {
        Reminder::factory()->create(['user_id' => $this->user->id]);
        Reminder::factory()->completed()->create(['user_id' => $this->user->id]);

        expect(Reminder::completed()->count())->toBe(1);
    });

    it('filters overdue reminders', function () {
        Reminder::factory()->overdue()->create(['user_id' => $this->user->id]);
        Reminder::factory()->create(['user_id' => $this->user->id, 'due_at' => now()->addDays(5)]);

        expect(Reminder::overdue()->count())->toBe(1);
    });

    it('filters upcoming reminders within days', function () {
        Reminder::factory()->create(['user_id' => $this->user->id, 'due_at' => now()->addDays(3)]);
        Reminder::factory()->create(['user_id' => $this->user->id, 'due_at' => now()->addDays(5)]);
        Reminder::factory()->create(['user_id' => $this->user->id, 'due_at' => now()->addDays(10)]);

        expect(Reminder::upcoming(7)->count())->toBe(2);
    });
});

describe('Reminder Actions', function () {
    it('can complete a reminder', function () {
        $reminder = Reminder::factory()->create(['user_id' => $this->user->id]);

        $reminder->complete();

        expect($reminder->fresh()->completed_at)->not->toBeNull();
    });

    it('creates next occurrence when completing recurring reminder', function () {
        Carbon::setTestNow(Carbon::create(2026, 1, 15, 10, 0, 0));

        $reminder = Reminder::factory()->recurring(ReminderRecurrence::Weekly)->create([
            'user_id' => $this->user->id,
            'due_at' => Carbon::create(2026, 1, 15),
        ]);

        $reminder->complete();

        $newReminder = Reminder::pending()->latest('id')->first();

        expect($newReminder)->not->toBeNull()
            ->and($newReminder->due_at->toDateString())->toBe('2026-01-22')
            ->and($newReminder->recurrence)->toBe(ReminderRecurrence::Weekly);

        Carbon::setTestNow();
    });

    it('can snooze a reminder', function () {
        Carbon::setTestNow(Carbon::create(2026, 1, 15, 10, 0, 0));

        $reminder = Reminder::factory()->create(['user_id' => $this->user->id]);

        $reminder->snooze(24);

        expect($reminder->fresh()->snoozed_until->toDateTimeString())->toBe('2026-01-16 10:00:00');

        Carbon::setTestNow();
    });

    it('can reopen a completed reminder', function () {
        $reminder = Reminder::factory()->completed()->create(['user_id' => $this->user->id]);

        $reminder->reopen();

        expect($reminder->fresh()->completed_at)->toBeNull()
            ->and($reminder->fresh()->snoozed_until)->toBeNull();
    });
});

describe('Reminder Global User Scope', function () {
    it('only shows reminders for current user', function () {
        $otherUser = User::factory()->create();

        Reminder::factory()->create(['user_id' => $this->user->id]);
        Reminder::factory()->count(2)->create(['user_id' => $otherUser->id]);

        expect(Reminder::count())->toBe(1);
    });
});

describe('ReminderService', function () {
    it('creates reminder for entity', function () {
        $service = app(ReminderService::class);

        $reminder = $service->createForEntity(
            $this->client,
            'Follow up with client',
            now()->addDays(7),
            'Description here',
            ReminderPriority::High
        );

        expect($reminder->title)->toBe('Follow up with client')
            ->and($reminder->remindable_type)->toBe(Client::class)
            ->and($reminder->remindable_id)->toBe($this->client->id)
            ->and($reminder->priority)->toBe(ReminderPriority::High);
    });

    it('creates overdue invoice reminder', function () {
        $invoice = Invoice::factory()->sent()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'due_at' => now()->subDays(5),
        ]);

        $service = app(ReminderService::class);
        $reminder = $service->createOverdueInvoiceReminder($invoice);

        expect($reminder->title)->toContain('Überfällige Rechnung')
            ->and($reminder->is_system)->toBeTrue()
            ->and($reminder->system_type)->toBe('overdue_invoice')
            ->and($reminder->priority)->toBe(ReminderPriority::High);
    });

    it('does not duplicate overdue invoice reminder', function () {
        $invoice = Invoice::factory()->sent()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'due_at' => now()->subDays(5),
        ]);

        $service = app(ReminderService::class);
        $service->createOverdueInvoiceReminder($invoice);
        $service->createOverdueInvoiceReminder($invoice);

        expect(Reminder::where('system_type', 'overdue_invoice')->count())->toBe(1);
    });

    it('creates offer followup reminder', function () {
        $project = Project::factory()->sent()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'offer_sent_at' => now()->subDays(3),
        ]);

        $service = app(ReminderService::class);
        $reminder = $service->createOfferFollowupReminder($project);

        expect($reminder->title)->toContain('Angebot nachfassen')
            ->and($reminder->is_system)->toBeTrue()
            ->and($reminder->system_type)->toBe('offer_followup');
    });
});

describe('CheckOverdueInvoices Command', function () {
    it('creates reminders for overdue invoices', function () {
        $invoice = Invoice::factory()->sent()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'due_at' => now()->subDays(5),
        ]);

        $this->artisan('invoices:check-overdue')
            ->assertSuccessful();

        expect(Reminder::where('system_type', 'overdue_invoice')->count())->toBe(1);
    });
});

describe('Model Relationships', function () {
    it('client has reminders relationship', function () {
        $reminder = Reminder::factory()->create([
            'user_id' => $this->user->id,
            'remindable_type' => Client::class,
            'remindable_id' => $this->client->id,
        ]);

        expect($this->client->reminders)->toHaveCount(1)
            ->and($this->client->reminders->first()->id)->toBe($reminder->id);
    });

    it('project has reminders relationship', function () {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $reminder = Reminder::factory()->create([
            'user_id' => $this->user->id,
            'remindable_type' => Project::class,
            'remindable_id' => $project->id,
        ]);

        expect($project->reminders)->toHaveCount(1)
            ->and($project->reminders->first()->id)->toBe($reminder->id);
    });

    it('invoice has reminders relationship', function () {
        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $reminder = Reminder::factory()->create([
            'user_id' => $this->user->id,
            'remindable_type' => Invoice::class,
            'remindable_id' => $invoice->id,
        ]);

        expect($invoice->reminders)->toHaveCount(1)
            ->and($invoice->reminders->first()->id)->toBe($reminder->id);
    });
});
