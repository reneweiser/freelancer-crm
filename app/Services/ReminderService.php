<?php

namespace App\Services;

use App\Enums\ReminderPriority;
use App\Enums\ReminderRecurrence;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Reminder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ReminderService
{
    /**
     * Create a reminder for an entity.
     */
    public function createForEntity(
        Model $entity,
        string $title,
        Carbon $dueAt,
        ?string $description = null,
        ReminderPriority $priority = ReminderPriority::Normal,
        ?ReminderRecurrence $recurrence = null
    ): Reminder {
        return Reminder::create([
            'user_id' => auth()->id(),
            'remindable_type' => get_class($entity),
            'remindable_id' => $entity->id,
            'title' => $title,
            'description' => $description,
            'due_at' => $dueAt,
            'priority' => $priority,
            'recurrence' => $recurrence,
        ]);
    }

    /**
     * Create system-generated reminder for overdue invoice.
     */
    public function createOverdueInvoiceReminder(Invoice $invoice): Reminder
    {
        // Check if one already exists
        $existing = Reminder::withoutGlobalScope('user')
            ->where('user_id', $invoice->user_id)
            ->where('remindable_type', Invoice::class)
            ->where('remindable_id', $invoice->id)
            ->where('system_type', 'overdue_invoice')
            ->pending()
            ->first();

        if ($existing) {
            return $existing;
        }

        return Reminder::withoutGlobalScope('user')->create([
            'user_id' => $invoice->user_id,
            'remindable_type' => Invoice::class,
            'remindable_id' => $invoice->id,
            'title' => "Überfällige Rechnung: {$invoice->number}",
            'description' => "Rechnung {$invoice->number} ist seit dem {$invoice->due_at->format('d.m.Y')} überfällig. Betrag: {$invoice->formatted_total}",
            'due_at' => now(),
            'priority' => ReminderPriority::High,
            'is_system' => true,
            'system_type' => 'overdue_invoice',
        ]);
    }

    /**
     * Create follow-up reminder for sent offer.
     */
    public function createOfferFollowupReminder(Project $project, int $daysAfterSend = 7): Reminder
    {
        // Check if one already exists
        $existing = Reminder::withoutGlobalScope('user')
            ->where('user_id', $project->user_id)
            ->where('remindable_type', Project::class)
            ->where('remindable_id', $project->id)
            ->where('system_type', 'offer_followup')
            ->pending()
            ->first();

        if ($existing) {
            return $existing;
        }

        return Reminder::withoutGlobalScope('user')->create([
            'user_id' => $project->user_id,
            'remindable_type' => Project::class,
            'remindable_id' => $project->id,
            'title' => "Angebot nachfassen: {$project->title}",
            'description' => "Das Angebot für {$project->client->display_name} wurde am {$project->offer_sent_at->format('d.m.Y')} versendet. Zeit für ein Follow-up.",
            'due_at' => $project->offer_sent_at->addDays($daysAfterSend),
            'priority' => ReminderPriority::Normal,
            'is_system' => true,
            'system_type' => 'offer_followup',
        ]);
    }
}
