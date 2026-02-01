# Business Workflows & State Machines

## Project Lifecycle

### State Machine

```
                    ┌─────────────────────────────────────────────────┐
                    │                                                 │
                    ▼                                                 │
┌─────────┐     ┌─────────┐     ┌──────────┐     ┌─────────────┐     │
│  DRAFT  │────▶│  SENT   │────▶│ ACCEPTED │────▶│ IN_PROGRESS │─────┤
└─────────┘     └─────────┘     └──────────┘     └─────────────┘     │
     │               │                                  │             │
     │               │                                  │             │
     │               ▼                                  ▼             │
     │          ┌──────────┐                     ┌───────────┐        │
     │          │ DECLINED │                     │ COMPLETED │────────┘
     │          └──────────┘                     └───────────┘
     │                                                 │
     │                                                 │
     ▼                                                 ▼
┌───────────┐                                    ┌───────────┐
│ CANCELLED │◀───────────────────────────────────│(can cancel│
└───────────┘                                    │ any state)│
                                                 └───────────┘
```

### Status Definitions

| Status | Description | Allowed Transitions |
|--------|-------------|---------------------|
| `draft` | Offer being prepared | → sent, cancelled |
| `sent` | Offer sent to client | → accepted, declined, cancelled |
| `accepted` | Client accepted offer | → in_progress, cancelled |
| `declined` | Client declined offer | (terminal) |
| `in_progress` | Work is ongoing | → completed, cancelled |
| `completed` | Project finished | → in_progress (reopened) |
| `cancelled` | Project cancelled | (terminal) |

### ProjectStatus Enum

```php
enum ProjectStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Draft => 'Entwurf',
            self::Sent => 'Gesendet',
            self::Accepted => 'Angenommen',
            self::Declined => 'Abgelehnt',
            self::InProgress => 'In Bearbeitung',
            self::Completed => 'Abgeschlossen',
            self::Cancelled => 'Storniert',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Draft => 'gray',
            self::Sent => 'info',
            self::Accepted => 'success',
            self::Declined => 'danger',
            self::InProgress => 'warning',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function allowedTransitions(): array
    {
        return match($this) {
            self::Draft => [self::Sent, self::Cancelled],
            self::Sent => [self::Accepted, self::Declined, self::Cancelled],
            self::Accepted => [self::InProgress, self::Cancelled],
            self::Declined => [],
            self::InProgress => [self::Completed, self::Cancelled],
            self::Completed => [self::InProgress], // Reopen
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions());
    }
}
```

---

## Invoice Lifecycle

### State Machine

```
┌─────────┐     ┌─────────┐     ┌─────────┐
│  DRAFT  │────▶│  SENT   │────▶│  PAID   │
└─────────┘     └─────────┘     └─────────┘
     │               │
     │               │ (14+ days)
     │               ▼
     │          ┌─────────┐
     │          │ OVERDUE │─────▶ PAID
     │          └─────────┘
     │
     ▼
┌───────────┐
│ CANCELLED │◀──────────────── (any non-paid state)
└───────────┘
```

### Status Definitions

| Status | Description | Allowed Transitions |
|--------|-------------|---------------------|
| `draft` | Invoice being prepared | → sent, cancelled |
| `sent` | Invoice sent to client | → paid, overdue, cancelled |
| `overdue` | Past due date, unpaid | → paid, cancelled |
| `paid` | Payment received | (terminal) |
| `cancelled` | Invoice cancelled/storniert | (terminal) |

### InvoiceStatus Enum

```php
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Overdue = 'overdue';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Draft => 'Entwurf',
            self::Sent => 'Gesendet',
            self::Overdue => 'Überfällig',
            self::Paid => 'Bezahlt',
            self::Cancelled => 'Storniert',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Draft => 'gray',
            self::Sent => 'info',
            self::Overdue => 'danger',
            self::Paid => 'success',
            self::Cancelled => 'gray',
        };
    }
}
```

---

## Offer → Invoice Conversion

### Flow

```
┌────────────────┐     ┌────────────────┐     ┌────────────────┐
│    Project     │     │    Project     │     │    Invoice     │
│   (accepted)   │────▶│   items[]      │────▶│   items[]      │
└────────────────┘     └────────────────┘     └────────────────┘
                              │
                              │ Copy with option to:
                              │ - Add time entries (hourly)
                              │ - Modify quantities
                              │ - Add/remove items
                              ▼
                       ┌────────────────┐
                       │  Invoice Form  │
                       │   (pre-filled) │
                       └────────────────┘
```

### Service Implementation

```php
class InvoiceCreationService
{
    public function createFromProject(Project $project): Invoice
    {
        return DB::transaction(function () use ($project) {
            $invoice = Invoice::create([
                'user_id' => auth()->id(),
                'client_id' => $project->client_id,
                'project_id' => $project->id,
                'number' => Invoice::generateNextNumber(),
                'status' => InvoiceStatus::Draft,
                'issued_at' => now(),
                'due_at' => now()->addDays(14),
                'service_period_start' => $project->start_date,
                'service_period_end' => $project->end_date ?? now(),
            ]);

            // Copy project items
            foreach ($project->items as $item) {
                $invoice->items()->create([
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                    'unit_price' => $item->unit_price,
                    'vat_rate' => 19.00,
                ]);
            }

            // For hourly projects, add time entries
            if ($project->type === 'hourly') {
                $unbilledTime = $project->timeEntries()
                    ->where('billable', true)
                    ->whereNull('invoice_id')
                    ->get();

                if ($unbilledTime->isNotEmpty()) {
                    $totalHours = $unbilledTime->sum('duration_minutes') / 60;

                    $invoice->items()->create([
                        'description' => 'Arbeitszeit',
                        'quantity' => round($totalHours, 2),
                        'unit' => 'Stunden',
                        'unit_price' => $project->hourly_rate,
                        'vat_rate' => 19.00,
                    ]);

                    // Mark time entries as invoiced
                    $unbilledTime->each->update(['invoice_id' => $invoice->id]);
                }
            }

            $invoice->calculateTotals();

            return $invoice;
        });
    }
}
```

---

## Reminder System

### Trigger Points

| Trigger | Condition | Action |
|---------|-----------|--------|
| Offer Follow-up | Offer sent 3 days ago, no response | Create reminder to follow up |
| Invoice Overdue | Invoice past due_at, unpaid | Create reminder + mark overdue |
| Project Check-in | Project in_progress > 7 days no activity | Create check-in reminder |
| Post-Project Review | Project completed 30 days ago | Create review reminder |
| Recurring Task | next_due_at reached | Create task reminder, schedule next |

### Scheduler Commands

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    // Check for overdue invoices daily
    $schedule->command('invoices:check-overdue')
        ->dailyAt('08:00');

    // Process recurring tasks
    $schedule->command('tasks:process-recurring')
        ->dailyAt('07:00');

    // Send reminder notifications
    $schedule->command('reminders:send-notifications')
        ->everyFifteenMinutes();

    // Post-project review check
    $schedule->command('projects:check-reviews')
        ->dailyAt('09:00');
}
```

### Reminder Service

```php
class ReminderService
{
    public function createInvoiceOverdueReminder(Invoice $invoice): Reminder
    {
        return Reminder::firstOrCreate([
            'remindable_type' => Invoice::class,
            'remindable_id' => $invoice->id,
            'title' => "Rechnung {$invoice->number} überfällig",
        ], [
            'user_id' => $invoice->user_id,
            'description' => "Die Rechnung an {$invoice->client->display_name} ist seit dem {$invoice->due_at->format('d.m.Y')} überfällig.",
            'due_at' => now(),
        ]);
    }

    public function createPostProjectReviewReminder(Project $project): Reminder
    {
        return Reminder::create([
            'user_id' => $project->user_id,
            'remindable_type' => Project::class,
            'remindable_id' => $project->id,
            'title' => "Projekt-Nachverfolgung: {$project->title}",
            'description' => "30 Tage nach Projektabschluss. Ist der Kunde zufrieden? Gibt es Folgeprojekte?",
            'due_at' => now()->addDays(30),
        ]);
    }

    public function processRecurringTask(RecurringTask $task): void
    {
        // Create a reminder for the task
        Reminder::create([
            'user_id' => $task->user_id,
            'remindable_type' => RecurringTask::class,
            'remindable_id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'due_at' => $task->next_due_at,
        ]);

        // Calculate next occurrence
        $task->update([
            'last_run_at' => now(),
            'next_due_at' => $this->calculateNextDueDate($task),
        ]);
    }

    private function calculateNextDueDate(RecurringTask $task): Carbon
    {
        return match($task->frequency) {
            'weekly' => $task->next_due_at->addWeek(),
            'monthly' => $task->next_due_at->addMonth(),
            'quarterly' => $task->next_due_at->addMonths(3),
            'yearly' => $task->next_due_at->addYear(),
        };
    }
}
```

---

## PDF Generation Flow

### Offer PDF

```
Project → OfferPdfService → Blade Template → DomPDF → Storage/Download

Template includes:
- Your business details (from settings)
- Client address
- Offer number, date, validity
- Project description
- Line items with subtotals
- Total amount
- Terms and conditions
```

### Invoice PDF

```
Invoice → InvoicePdfService → Blade Template → DomPDF → Storage/Download

Template includes (German Rechnungspflichtangaben):
- Your full business name and address
- Your tax number (Steuernummer) or VAT ID (USt-IdNr.)
- Client full name/company and address
- Client VAT ID (if applicable)
- Invoice number
- Invoice date (Rechnungsdatum)
- Service period (Leistungszeitraum)
- Line items with:
  - Description
  - Quantity and unit
  - Unit price (net)
  - VAT rate per item
  - Line total
- Subtotal (net)
- VAT breakdown by rate
- Total (gross)
- Payment terms
- Bank details
```

### PDF Service

```php
class PdfService
{
    public function generateInvoicePdf(Invoice $invoice): string
    {
        $settings = $invoice->user->settings;

        $pdf = PDF::loadView('pdfs.invoice', [
            'invoice' => $invoice,
            'business' => [
                'name' => $settings->get('business_name'),
                'address' => $settings->get('business_address'),
                'tax_number' => $settings->get('tax_number'),
                'vat_id' => $settings->get('vat_id'),
                'bank_name' => $settings->get('bank_name'),
                'iban' => $settings->get('iban'),
                'bic' => $settings->get('bic'),
            ],
        ]);

        $filename = "Rechnung-{$invoice->number}.pdf";
        $path = "invoices/{$invoice->id}/{$filename}";

        Storage::put($path, $pdf->output());

        return $path;
    }
}
```
