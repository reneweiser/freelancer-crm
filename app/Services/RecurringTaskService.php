<?php

namespace App\Services;

use App\Enums\ReminderPriority;
use App\Models\RecurringTask;
use App\Models\RecurringTaskLog;
use App\Models\Reminder;
use Illuminate\Support\Facades\Log;

class RecurringTaskService
{
    public function __construct(
        private ReminderService $reminderService
    ) {}

    /**
     * Process all due recurring tasks.
     * Called by scheduler.
     */
    public function processDueTasks(): int
    {
        $processed = 0;

        // Use withoutGlobalScope for scheduler context (no auth user)
        $dueTasks = RecurringTask::withoutGlobalScope('user')
            ->with('client')
            ->active()
            ->where('next_due_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->get();

        foreach ($dueTasks as $task) {
            $this->processTask($task);
            $processed++;
        }

        return $processed;
    }

    /**
     * Process a single recurring task.
     */
    public function processTask(RecurringTask $task): Reminder
    {
        // Create reminder for this occurrence
        $reminder = Reminder::withoutGlobalScope('user')->create([
            'user_id' => $task->user_id,
            'remindable_type' => RecurringTask::class,
            'remindable_id' => $task->id,
            'title' => $task->title,
            'description' => $this->buildReminderDescription($task),
            'due_at' => $task->next_due_at->startOfDay()->setHour(9),
            'priority' => ReminderPriority::Normal,
            'is_system' => true,
            'system_type' => 'recurring_task',
        ]);

        // Log the execution
        RecurringTaskLog::create([
            'recurring_task_id' => $task->id,
            'due_date' => $task->next_due_at,
            'action' => 'reminder_created',
            'reminder_id' => $reminder->id,
        ]);

        // Advance to next occurrence
        $task->advance();

        Log::info("Processed recurring task: {$task->title}");

        return $reminder;
    }

    /**
     * Create upcoming reminders for tasks due soon.
     * Called by scheduler to create reminders before due date.
     */
    public function createUpcomingReminders(): int
    {
        $created = 0;

        // Use withoutGlobalScope for scheduler context
        $tasks = RecurringTask::withoutGlobalScope('user')
            ->with('client')
            ->active()
            ->whereDoesntHave('reminders', function ($q) {
                $q->pending();
            })
            ->get()
            ->filter(fn ($task) => $task->is_due_soon);

        foreach ($tasks as $task) {
            $daysBeforeDue = $task->frequency->daysBefore();

            Reminder::withoutGlobalScope('user')->create([
                'user_id' => $task->user_id,
                'remindable_type' => RecurringTask::class,
                'remindable_id' => $task->id,
                'title' => "Anstehend: {$task->title}",
                'description' => $this->buildReminderDescription($task),
                'due_at' => $task->next_due_at->copy()->subDays($daysBeforeDue)->startOfDay()->setHour(9),
                'priority' => ReminderPriority::Normal,
                'is_system' => true,
                'system_type' => 'recurring_task_upcoming',
            ]);

            $created++;
        }

        return $created;
    }

    /**
     * Build description text for reminder.
     */
    private function buildReminderDescription(RecurringTask $task): string
    {
        $parts = [];

        if ($task->client) {
            $parts[] = "Kunde: {$task->client->display_name}";
        }

        $parts[] = "Frequenz: {$task->frequency->getLabel()}";
        $parts[] = "Fällig: {$task->next_due_at->format('d.m.Y')}";

        if ($task->amount) {
            $parts[] = "Betrag: {$task->formatted_amount}";
        }

        if ($task->description) {
            $parts[] = '';
            $parts[] = $task->description;
        }

        return implode("\n", $parts);
    }

    /**
     * Skip current occurrence without processing.
     */
    public function skipOccurrence(RecurringTask $task, ?string $reason = null): void
    {
        RecurringTaskLog::create([
            'recurring_task_id' => $task->id,
            'due_date' => $task->next_due_at,
            'action' => 'skipped',
            'notes' => $reason,
        ]);

        $task->advance();

        Log::info("Skipped recurring task: {$task->title}");
    }
}
