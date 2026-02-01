<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\ReminderService;
use Illuminate\Console\Command;

class CheckOverdueInvoices extends Command
{
    protected $signature = 'invoices:check-overdue';

    protected $description = 'Mark sent invoices as overdue when past due date and create reminders';

    public function __construct(private ReminderService $reminderService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $overdueInvoices = Invoice::query()
            ->withoutGlobalScope('user')
            ->where('status', InvoiceStatus::Sent)
            ->whereDate('due_at', '<', now())
            ->get();

        $count = $overdueInvoices->count();

        if ($count === 0) {
            $this->info('Keine überfälligen Rechnungen gefunden.');

            return self::SUCCESS;
        }

        $remindersCreated = 0;
        $overdueInvoices->each(function (Invoice $invoice) use (&$remindersCreated): void {
            $invoice->update(['status' => InvoiceStatus::Overdue]);

            $reminder = $this->reminderService->createOverdueInvoiceReminder($invoice);
            if ($reminder->wasRecentlyCreated) {
                $remindersCreated++;
            }
        });

        $this->info("{$count} Rechnung(en) als überfällig markiert.");
        $this->info("{$remindersCreated} Erinnerung(en) erstellt.");

        return self::SUCCESS;
    }
}
