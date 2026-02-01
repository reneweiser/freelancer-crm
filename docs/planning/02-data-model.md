# Data Model

## Entity Relationship Diagram (Conceptual)

```
┌─────────────┐       ┌─────────────┐       ┌─────────────┐
│    User     │       │   Client    │       │   Project   │
├─────────────┤       ├─────────────┤       ├─────────────┤
│ id          │       │ id          │       │ id          │
│ name        │       │ user_id     │◄──────│ client_id   │
│ email       │       │ type        │       │ user_id     │
│ password    │       │ company_name│       │ title       │
│ role        │       │ contact_name│       │ description │
│ created_at  │       │ email       │       │ type        │
│ updated_at  │       │ phone       │       │ status      │
└─────────────┘       │ address     │       │ hourly_rate │
       │              │ vat_id      │       │ fixed_price │
       │              │ notes       │       │ offer_*     │
       │              │ created_at  │       │ created_at  │
       │              │ updated_at  │       │ updated_at  │
       │              └─────────────┘       └─────────────┘
       │                     │                     │
       │                     │                     │
       ▼                     ▼                     ▼
┌─────────────┐       ┌─────────────┐       ┌─────────────┐
│ TimeEntry   │       │   Invoice   │       │ ProjectItem │
├─────────────┤       ├─────────────┤       ├─────────────┤
│ id          │       │ id          │       │ id          │
│ user_id     │       │ client_id   │       │ project_id  │
│ project_id  │       │ project_id  │       │ description │
│ description │       │ number      │       │ quantity    │
│ started_at  │       │ status      │       │ unit        │
│ ended_at    │       │ issued_at   │       │ unit_price  │
│ duration_min│       │ due_at      │       │ position    │
│ billable    │       │ paid_at     │       └─────────────┘
│ created_at  │       │ subtotal    │
│ updated_at  │       │ vat_rate    │       ┌─────────────┐
└─────────────┘       │ vat_amount  │       │InvoiceItem  │
                      │ total       │       ├─────────────┤
                      │ notes       │       │ id          │
┌─────────────┐       │ created_at  │       │ invoice_id  │
│  Reminder   │       │ updated_at  │       │ description │
├─────────────┤       └─────────────┘       │ quantity    │
│ id          │                             │ unit        │
│ user_id     │                             │ unit_price  │
│ remindable  │◄────────────────────────────│ vat_rate    │
│ title       │  (polymorphic)              │ position    │
│ due_at      │                             └─────────────┘
│ completed_at│
│ recurrence  │       ┌─────────────┐
│ created_at  │       │RecurringTask│
│ updated_at  │       ├─────────────┤
└─────────────┘       │ id          │
                      │ user_id     │
                      │ client_id   │
                      │ title       │
                      │ description │
                      │ frequency   │
                      │ next_due_at │
                      │ last_run_at │
                      │ active      │
                      │ created_at  │
                      │ updated_at  │
                      └─────────────┘
```

---

## Table Definitions

### users
Standard Laravel authentication with role extension.

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->enum('role', ['owner', 'member'])->default('member');
    $table->rememberToken();
    $table->timestamps();
});
```

### clients
Clients can be companies or individuals.

```php
Schema::create('clients', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // Creator
    $table->enum('type', ['company', 'individual'])->default('company');

    // Company info
    $table->string('company_name')->nullable();
    $table->string('vat_id')->nullable(); // USt-IdNr.

    // Contact info
    $table->string('contact_name');
    $table->string('email')->nullable();
    $table->string('phone')->nullable();

    // Address
    $table->string('street')->nullable();
    $table->string('postal_code')->nullable();
    $table->string('city')->nullable();
    $table->string('country')->default('DE');

    // Meta
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->index(['user_id', 'company_name']);
});
```

### projects
Projects track the workflow from offer to completion.

```php
Schema::create('projects', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('client_id')->constrained()->cascadeOnDelete();

    // Basic info
    $table->string('title');
    $table->text('description')->nullable();
    $table->string('reference')->nullable(); // Internal reference number

    // Pricing
    $table->enum('type', ['hourly', 'fixed'])->default('fixed');
    $table->decimal('hourly_rate', 10, 2)->nullable();
    $table->decimal('fixed_price', 10, 2)->nullable();

    // Workflow status
    $table->enum('status', [
        'draft',      // Offer being prepared
        'sent',       // Offer sent to client
        'accepted',   // Client accepted, project active
        'declined',   // Client declined offer
        'in_progress',// Work ongoing
        'completed',  // Project finished
        'cancelled',  // Project cancelled
    ])->default('draft');

    // Offer details
    $table->date('offer_date')->nullable();
    $table->date('offer_valid_until')->nullable();
    $table->timestamp('offer_sent_at')->nullable();
    $table->timestamp('offer_accepted_at')->nullable();

    // Project dates
    $table->date('start_date')->nullable();
    $table->date('end_date')->nullable();

    // Notes
    $table->text('notes')->nullable();

    $table->timestamps();
    $table->softDeletes();

    $table->index(['user_id', 'status']);
    $table->index(['client_id', 'status']);
});
```

### project_items
Line items for project offers (services/deliverables).

```php
Schema::create('project_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->constrained()->cascadeOnDelete();

    $table->string('description');
    $table->decimal('quantity', 10, 2)->default(1);
    $table->string('unit')->default('Stück'); // Stück, Stunden, Tage, Pauschal
    $table->decimal('unit_price', 10, 2);

    $table->unsignedInteger('position')->default(0);
    $table->timestamps();

    $table->index('project_id');
});
```

### time_entries
Time tracking for hourly projects.

```php
Schema::create('time_entries', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('project_id')->constrained()->cascadeOnDelete();

    $table->text('description')->nullable();
    $table->timestamp('started_at');
    $table->timestamp('ended_at')->nullable();
    $table->unsignedInteger('duration_minutes')->nullable(); // Calculated or manual
    $table->boolean('billable')->default(true);

    $table->timestamps();

    $table->index(['project_id', 'started_at']);
    $table->index(['user_id', 'started_at']);
});
```

### invoices
Invoice header with German compliance fields.

```php
Schema::create('invoices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('client_id')->constrained()->cascadeOnDelete();
    $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();

    // Invoice number (e.g., 2026-001)
    $table->string('number')->unique();

    // Status
    $table->enum('status', [
        'draft',
        'sent',
        'paid',
        'overdue',
        'cancelled',
    ])->default('draft');

    // Dates
    $table->date('issued_at');
    $table->date('due_at');
    $table->date('paid_at')->nullable();
    $table->string('payment_method')->nullable();

    // Amounts (calculated from items)
    $table->decimal('subtotal', 12, 2)->default(0);
    $table->decimal('vat_rate', 5, 2)->default(19.00); // German standard VAT
    $table->decimal('vat_amount', 12, 2)->default(0);
    $table->decimal('total', 12, 2)->default(0);

    // Service period (Leistungszeitraum)
    $table->date('service_period_start')->nullable();
    $table->date('service_period_end')->nullable();

    // Notes
    $table->text('notes')->nullable();
    $table->text('footer_text')->nullable();

    $table->timestamps();
    $table->softDeletes();

    $table->index(['user_id', 'status']);
    $table->index(['client_id', 'issued_at']);
    $table->index('issued_at');
});
```

### invoice_items
Invoice line items with individual VAT support.

```php
Schema::create('invoice_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();

    $table->string('description');
    $table->decimal('quantity', 10, 2)->default(1);
    $table->string('unit')->default('Stück');
    $table->decimal('unit_price', 10, 2);
    $table->decimal('vat_rate', 5, 2)->default(19.00);

    $table->unsignedInteger('position')->default(0);
    $table->timestamps();

    $table->index('invoice_id');
});
```

### reminders
Polymorphic reminders for any entity.

```php
Schema::create('reminders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();

    // Polymorphic relation
    $table->nullableMorphs('remindable');

    $table->string('title');
    $table->text('description')->nullable();
    $table->timestamp('due_at');
    $table->timestamp('completed_at')->nullable();

    // Recurrence (null = one-time)
    $table->enum('recurrence', ['daily', 'weekly', 'monthly', 'yearly'])->nullable();

    $table->timestamps();

    $table->index(['user_id', 'due_at']);
    $table->index(['remindable_type', 'remindable_id']);
});
```

### recurring_tasks
Maintenance contracts and recurring work.

```php
Schema::create('recurring_tasks', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();

    $table->string('title');
    $table->text('description')->nullable();

    $table->enum('frequency', ['weekly', 'monthly', 'quarterly', 'yearly']);
    $table->date('next_due_at');
    $table->date('last_run_at')->nullable();

    $table->boolean('active')->default(true);

    $table->timestamps();

    $table->index(['user_id', 'active', 'next_due_at']);
});
```

### settings
Key-value store for user settings.

```php
Schema::create('settings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('key');
    $table->text('value')->nullable();
    $table->timestamps();

    $table->unique(['user_id', 'key']);
});
```

---

## Eloquent Models

### Key Relationships

```php
// User
public function clients(): HasMany
public function projects(): HasMany
public function invoices(): HasMany
public function timeEntries(): HasMany
public function reminders(): HasMany
public function recurringTasks(): HasMany

// Client
public function user(): BelongsTo
public function projects(): HasMany
public function invoices(): HasMany
public function recurringTasks(): HasMany
public function reminders(): MorphMany

// Project
public function user(): BelongsTo
public function client(): BelongsTo
public function items(): HasMany
public function timeEntries(): HasMany
public function invoices(): HasMany
public function reminders(): MorphMany

// Invoice
public function user(): BelongsTo
public function client(): BelongsTo
public function project(): BelongsTo
public function items(): HasMany
public function reminders(): MorphMany

// TimeEntry
public function user(): BelongsTo
public function project(): BelongsTo

// Reminder
public function user(): BelongsTo
public function remindable(): MorphTo

// RecurringTask
public function user(): BelongsTo
public function client(): BelongsTo
```

### Scopes

```php
// Project scopes
public function scopeActive($query)
public function scopeOffers($query)
public function scopeByStatus($query, string $status)

// Invoice scopes
public function scopeUnpaid($query)
public function scopeOverdue($query)
public function scopeByYear($query, int $year)
public function scopeByDateRange($query, $start, $end)

// Reminder scopes
public function scopeUpcoming($query)
public function scopePending($query)
public function scopeOverdue($query)
```

### Casts & Accessors

```php
// Invoice
protected $casts = [
    'issued_at' => 'date',
    'due_at' => 'date',
    'paid_at' => 'date',
    'subtotal' => 'decimal:2',
    'vat_amount' => 'decimal:2',
    'total' => 'decimal:2',
    'status' => InvoiceStatus::class, // Enum
];

// Money formatting accessor
public function getFormattedTotalAttribute(): string
{
    return Number::currency($this->total, 'EUR', 'de_DE');
}
```

---

## Indexes Strategy

| Table | Index | Purpose |
|-------|-------|---------|
| clients | user_id, company_name | List clients for user |
| projects | user_id, status | Filter projects by status |
| projects | client_id, status | Client's projects |
| invoices | user_id, status | Dashboard unpaid invoices |
| invoices | issued_at | Tax export date range |
| time_entries | project_id, started_at | Project time totals |
| reminders | user_id, due_at | Upcoming reminders |
