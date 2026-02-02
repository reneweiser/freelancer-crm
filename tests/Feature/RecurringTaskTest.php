<?php

use App\Enums\TaskFrequency;
use App\Models\Client;
use App\Models\RecurringTask;
use App\Models\RecurringTaskLog;
use App\Models\Reminder;
use App\Models\User;
use App\Services\RecurringTaskService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->client = Client::factory()->create(['user_id' => $this->user->id]);
    $this->actingAs($this->user);
});

describe('RecurringTask Model', function () {
    it('can create a basic recurring task', function () {
        $task = RecurringTask::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Website-Wartung',
            'frequency' => TaskFrequency::Monthly,
            'next_due_at' => now()->addMonth(),
        ]);

        expect($task->title)->toBe('Website-Wartung')
            ->and($task->frequency)->toBe(TaskFrequency::Monthly)
            ->and($task->active)->toBeTrue();
    });

    it('can attach task to a client', function () {
        $task = RecurringTask::factory()->forClient($this->client)->create();

        expect($task->client)->toBeInstanceOf(Client::class)
            ->and($task->client->id)->toBe($this->client->id);
    });

    it('detects overdue tasks', function () {
        $task = RecurringTask::factory()->overdue()->create([
            'user_id' => $this->user->id,
        ]);

        expect($task->is_overdue)->toBeTrue();
    });

    it('detects tasks due soon', function () {
        $task = RecurringTask::factory()->monthly()->create([
            'user_id' => $this->user->id,
            'next_due_at' => now()->addDays(5),
        ]);

        // Monthly tasks have daysBefore = 7, so 5 days is within threshold
        expect($task->is_due_soon)->toBeTrue();
    });

    it('formats amount correctly', function () {
        $task = RecurringTask::factory()->withAmount()->create([
            'user_id' => $this->user->id,
            'amount' => 199.99,
        ]);

        expect($task->formatted_amount)->toContain('199,99')
            ->and($task->formatted_amount)->toContain('€');
    });

    it('detects ended contracts', function () {
        $task = RecurringTask::factory()->expired()->create([
            'user_id' => $this->user->id,
        ]);

        expect($task->has_ended)->toBeTrue();
    });
});

describe('RecurringTask Scopes', function () {
    it('filters active tasks', function () {
        RecurringTask::factory()->create(['user_id' => $this->user->id, 'active' => true]);
        RecurringTask::factory()->inactive()->create(['user_id' => $this->user->id]);

        expect(RecurringTask::active()->count())->toBe(1);
    });

    it('filters inactive tasks', function () {
        RecurringTask::factory()->create(['user_id' => $this->user->id, 'active' => true]);
        RecurringTask::factory()->inactive()->create(['user_id' => $this->user->id]);

        expect(RecurringTask::inactive()->count())->toBe(1);
    });

    it('filters overdue tasks', function () {
        RecurringTask::factory()->overdue()->create(['user_id' => $this->user->id]);
        RecurringTask::factory()->create(['user_id' => $this->user->id, 'next_due_at' => now()->addDays(10)]);

        expect(RecurringTask::overdue()->count())->toBe(1);
    });

    it('filters tasks due soon', function () {
        RecurringTask::factory()->create(['user_id' => $this->user->id, 'next_due_at' => now()->addDays(3)]);
        RecurringTask::factory()->create(['user_id' => $this->user->id, 'next_due_at' => now()->addDays(30)]);

        expect(RecurringTask::dueSoon(7)->count())->toBe(1);
    });
});

describe('RecurringTask Actions', function () {
    it('advances task to next due date', function () {
        Carbon::setTestNow(Carbon::create(2026, 1, 15));

        $task = RecurringTask::factory()->monthly()->create([
            'user_id' => $this->user->id,
            'next_due_at' => Carbon::create(2026, 1, 15),
        ]);

        $task->advance();
        $task->refresh();

        expect($task->last_run_at->toDateString())->toBe('2026-01-15')
            ->and($task->next_due_at->toDateString())->toBe('2026-02-15');

        Carbon::setTestNow();
    });

    it('pauses a task', function () {
        $task = RecurringTask::factory()->create(['user_id' => $this->user->id, 'active' => true]);

        $task->pause();

        expect($task->fresh()->active)->toBeFalse();
    });

    it('resumes a paused task and adjusts due date if in past', function () {
        Carbon::setTestNow(Carbon::create(2026, 3, 15));

        $task = RecurringTask::factory()->monthly()->inactive()->create([
            'user_id' => $this->user->id,
            'next_due_at' => Carbon::create(2026, 1, 14), // More than 2 months ago
        ]);

        $task->resume();
        $task->refresh();

        expect($task->active)->toBeTrue()
            // Should be advanced to a future date (April 14, 2026 or later)
            ->and($task->next_due_at->gte(now()))->toBeTrue();

        Carbon::setTestNow();
    });

    it('deactivates task after end date when advancing', function () {
        Carbon::setTestNow(Carbon::create(2026, 1, 20));

        $task = RecurringTask::factory()->monthly()->create([
            'user_id' => $this->user->id,
            'next_due_at' => Carbon::create(2026, 1, 15),
            'ends_at' => Carbon::create(2026, 1, 16), // Already ended
        ]);

        $task->advance();
        $task->refresh();

        expect($task->active)->toBeFalse();

        Carbon::setTestNow();
    });
});

describe('RecurringTask Global User Scope', function () {
    it('only shows tasks for current user', function () {
        $otherUser = User::factory()->create();

        RecurringTask::factory()->create(['user_id' => $this->user->id]);
        RecurringTask::factory()->count(2)->create(['user_id' => $otherUser->id]);

        expect(RecurringTask::count())->toBe(1);
    });
});

describe('RecurringTaskService', function () {
    it('processes due tasks and creates reminders', function () {
        Carbon::setTestNow(Carbon::create(2026, 1, 15, 9, 0, 0));

        $task = RecurringTask::factory()->monthly()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'title' => 'Website-Wartung',
            'next_due_at' => Carbon::create(2026, 1, 14), // Yesterday
        ]);

        $service = app(RecurringTaskService::class);
        $reminder = $service->processTask($task);

        $task->refresh();

        expect($reminder)->toBeInstanceOf(Reminder::class)
            ->and($reminder->title)->toBe('Website-Wartung')
            ->and($reminder->is_system)->toBeTrue()
            ->and($reminder->system_type)->toBe('recurring_task')
            ->and($task->last_run_at->toDateString())->toBe('2026-01-14')
            ->and($task->next_due_at->toDateString())->toBe('2026-02-14');

        Carbon::setTestNow();
    });

    it('creates log entry when processing task', function () {
        $task = RecurringTask::factory()->overdue()->create([
            'user_id' => $this->user->id,
        ]);

        $service = app(RecurringTaskService::class);
        $service->processTask($task);

        expect(RecurringTaskLog::where('recurring_task_id', $task->id)->count())->toBe(1)
            ->and(RecurringTaskLog::first()->action)->toBe('reminder_created');
    });

    it('skips occurrence and creates log', function () {
        $task = RecurringTask::factory()->monthly()->create([
            'user_id' => $this->user->id,
            'next_due_at' => now(),
        ]);

        $originalDue = $task->next_due_at->copy();

        $service = app(RecurringTaskService::class);
        $service->skipOccurrence($task, 'Kunde im Urlaub');

        $task->refresh();
        $log = RecurringTaskLog::where('recurring_task_id', $task->id)->first();

        expect($task->next_due_at->gt($originalDue))->toBeTrue()
            ->and($log->action)->toBe('skipped')
            ->and($log->notes)->toBe('Kunde im Urlaub');
    });

    it('processes all due tasks', function () {
        RecurringTask::factory()->overdue()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        $service = app(RecurringTaskService::class);
        $processed = $service->processDueTasks();

        expect($processed)->toBe(3)
            ->and(Reminder::count())->toBe(3);
    });
});

describe('TaskFrequency Enum', function () {
    it('calculates next due date for weekly', function () {
        $date = Carbon::create(2026, 1, 15);
        $next = TaskFrequency::Weekly->nextDueDate($date);

        expect($next->toDateString())->toBe('2026-01-22');
    });

    it('calculates next due date for monthly', function () {
        $date = Carbon::create(2026, 1, 15);
        $next = TaskFrequency::Monthly->nextDueDate($date);

        expect($next->toDateString())->toBe('2026-02-15');
    });

    it('calculates next due date for quarterly', function () {
        $date = Carbon::create(2026, 1, 15);
        $next = TaskFrequency::Quarterly->nextDueDate($date);

        expect($next->toDateString())->toBe('2026-04-15');
    });

    it('calculates next due date for yearly', function () {
        $date = Carbon::create(2026, 1, 15);
        $next = TaskFrequency::Yearly->nextDueDate($date);

        expect($next->toDateString())->toBe('2027-01-15');
    });

    it('returns correct days before threshold', function () {
        expect(TaskFrequency::Weekly->daysBefore())->toBe(2)
            ->and(TaskFrequency::Monthly->daysBefore())->toBe(7)
            ->and(TaskFrequency::Quarterly->daysBefore())->toBe(14)
            ->and(TaskFrequency::Yearly->daysBefore())->toBe(30);
    });
});

describe('Model Relationships', function () {
    it('user has recurring tasks relationship', function () {
        $task = RecurringTask::factory()->create(['user_id' => $this->user->id]);

        expect($this->user->recurringTasks)->toHaveCount(1)
            ->and($this->user->recurringTasks->first()->id)->toBe($task->id);
    });

    it('client has recurring tasks relationship', function () {
        $task = RecurringTask::factory()->forClient($this->client)->create();

        expect($this->client->recurringTasks)->toHaveCount(1)
            ->and($this->client->recurringTasks->first()->id)->toBe($task->id);
    });

    it('recurring task has logs relationship', function () {
        $task = RecurringTask::factory()->create(['user_id' => $this->user->id]);
        $log = RecurringTaskLog::factory()->create(['recurring_task_id' => $task->id]);

        expect($task->logs)->toHaveCount(1)
            ->and($task->logs->first()->id)->toBe($log->id);
    });

    it('recurring task has reminders relationship', function () {
        $task = RecurringTask::factory()->create(['user_id' => $this->user->id]);
        $reminder = Reminder::factory()->create([
            'user_id' => $this->user->id,
            'remindable_type' => RecurringTask::class,
            'remindable_id' => $task->id,
        ]);

        expect($task->reminders)->toHaveCount(1)
            ->and($task->reminders->first()->id)->toBe($reminder->id);
    });
});
