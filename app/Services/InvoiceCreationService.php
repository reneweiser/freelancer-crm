<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\ProjectType;
use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

class InvoiceCreationService
{
    public function __construct(
        protected SettingsService $settings
    ) {}

    public function createFromProject(Project $project): Invoice
    {
        if (! $project->canBeInvoiced()) {
            throw new \InvalidArgumentException(
                "Projekt kann im Status '{$project->status->getLabel()}' nicht abgerechnet werden."
            );
        }

        return DB::transaction(function () use ($project) {
            $paymentTermDays = (int) ($this->settings->get('default_payment_terms', 14));
            $defaultVatRate = (float) ($this->settings->get('default_vat_rate', 19.00));

            $invoice = Invoice::create([
                'user_id' => $project->user_id,
                'client_id' => $project->client_id,
                'project_id' => $project->id,
                'number' => Invoice::generateNextNumber(),
                'status' => InvoiceStatus::Draft,
                'issued_at' => now(),
                'due_at' => now()->addDays($paymentTermDays),
                'vat_rate' => $defaultVatRate,
                'service_period_start' => $project->start_date,
                'service_period_end' => $project->end_date ?? now(),
            ]);

            $position = 1;

            // Copy project items
            foreach ($project->items as $item) {
                $invoice->items()->create([
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                    'unit_price' => $item->unit_price,
                    'vat_rate' => $defaultVatRate,
                    'position' => $position++,
                ]);
            }

            // For hourly projects, add time entries as line item
            if ($project->type === ProjectType::Hourly) {
                $this->addTimeEntriesLineItem($invoice, $project, $defaultVatRate, $position);
            }

            // Calculate totals
            $invoice->refresh();
            $invoice->calculateTotals();
            $invoice->save();

            return $invoice;
        });
    }

    protected function addTimeEntriesLineItem(Invoice $invoice, Project $project, float $vatRate, int $position): void
    {
        if ($project->hourly_rate === null) {
            return;
        }

        $unbilledTime = $project->timeEntries()
            ->billable()
            ->unbilled()
            ->orderBy('started_at')
            ->get();

        if ($unbilledTime->isEmpty()) {
            return;
        }

        $totalMinutes = $unbilledTime->sum('duration_minutes');
        $totalHours = round($totalMinutes / 60, 2);

        $earliestDate = $unbilledTime->min('started_at');
        $latestDate = $unbilledTime->max('started_at');

        $dateRange = $earliestDate->format('d.m.Y');
        if ($earliestDate->format('Y-m-d') !== $latestDate->format('Y-m-d')) {
            $dateRange .= ' - '.$latestDate->format('d.m.Y');
        }

        $invoice->items()->create([
            'description' => "Arbeitszeit ({$dateRange})",
            'quantity' => $totalHours,
            'unit' => 'Stunden',
            'unit_price' => $project->hourly_rate,
            'vat_rate' => $vatRate,
            'position' => $position,
        ]);

        $unbilledTime->each(fn ($entry) => $entry->update(['invoice_id' => $invoice->id]));
    }
}
