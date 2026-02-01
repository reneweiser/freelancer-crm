<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Console\Command;

class CheckOverdueInvoices extends Command
{
    protected $signature = 'invoices:check-overdue';

    protected $description = 'Mark sent invoices as overdue when past due date';

    public function handle(): int
    {
        $overdueInvoices = Invoice::query()
            ->where('status', InvoiceStatus::Sent)
            ->whereDate('due_at', '<', now())
            ->get();

        $count = $overdueInvoices->count();

        if ($count === 0) {
            $this->info('Keine überfälligen Rechnungen gefunden.');

            return self::SUCCESS;
        }

        $overdueInvoices->each(function (Invoice $invoice): void {
            $invoice->update(['status' => InvoiceStatus::Overdue]);
        });

        $this->info("{$count} Rechnung(en) als überfällig markiert.");

        return self::SUCCESS;
    }
}
