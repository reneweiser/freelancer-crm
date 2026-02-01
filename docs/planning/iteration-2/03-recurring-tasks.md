# Recurring Tasks

## Overview

Track maintenance contracts, hosting renewals, and other recurring work with automatic reminder generation. This feature helps freelancers manage ongoing client relationships and ensures regular billing opportunities aren't missed.

## Features

| Feature | Description | Priority |
|---------|-------------|----------|
| RecurringTask Model | Tasks with frequency and next due date | P0 |
| RecurringTaskResource | Full CRUD via Filament | P0 |
| Scheduler Job | Advance tasks and create reminders | P0 |
| Client Association | Link tasks to specific clients | P0 |
| Dashboard Visibility | Show upcoming tasks on dashboard | P1 |
| Pause/Resume | Temporarily disable tasks | P1 |
| Task History | Log of past executions | P2 |

---

## Data Model

### recurring_tasks

Already defined in `02-data-model.md`, implementation with additions:

```php
Schema::create('recurring_tasks', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();

    $table->string('title');
    $table->text('description')->nullable();

    // Scheduling
    $table->enum('frequency', ['weekly', 'monthly', 'quarterly', 'yearly']);
    $table->date('next_due_at');
    $table->date('last_run_at')->nullable();
    $table->date('started_at')->nullable(); // When the contract started
    $table->date('ends_at')->nullable(); // Optional end date for fixed-term contracts

    // Billing info (optional, for reference)
    $table->decimal('amount', 10, 2)->nullable();
    $table->string('billing_notes')->nullable();

    // Status
    $table->boolean('active')->default(true);

    $table->timestamps();

    $table->index(['user_id', 'active', 'next_due_at']);
    $table->index(['client_id']);
});

// History log for tracking executions
Schema::create('recurring_task_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('recurring_task_id')->constrained()->cascadeOnDelete();

    $table->date('due_date');
    $table->enum('action', ['reminder_created', 'manually_completed', 'skipped']);
    $table->foreignId('reminder_id')->nullable()->constrained()->nullOnDelete();
    $table->text('notes')->nullable();

    $table->timestamps();

    $table->index(['recurring_task_id', 'due_date']);
});
```

---

## Enums

**Important:** All enums must implement Filament's `HasLabel` and `HasColor` contracts for proper badge rendering.

### TaskFrequency

```php
namespace App\Enums;

use Carbon\Carbon;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TaskFrequency: string implements HasLabel, HasColor
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';

    public function getLabel(): string
    {
        return match ($this) {
            self::Weekly => 'Wöchentlich',
            self::Monthly => 'Monatlich',
            self::Quarterly => 'Vierteljährlich',
            self::Yearly => 'Jährlich',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Weekly => 'info',
            self::Monthly => 'primary',
            self::Quarterly => 'warning',
            self::Yearly => 'success',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Weekly => 'Woche',
            self::Monthly => 'Monat',
            self::Quarterly => 'Quartal',
            self::Yearly => 'Jahr',
        };
    }

    public function nextDueDate(Carbon $from): Carbon
    {
        return match ($this) {
            self::Weekly => $from->copy()->addWeek(),
            self::Monthly => $from->copy()->addMonth(),
            self::Quarterly => $from->copy()->addQuarter(),
            self::Yearly => $from->copy()->addYear(),
        };
    }

    public function daysBefore(): int
    {
        // Days before due date to create reminder
        return match ($this) {
            self::Weekly => 2,
            self::Monthly => 7,
            self::Quarterly => 14,
            self::Yearly => 30,
        };
    }
}
```

---

## Models

### RecurringTask

```php
namespace App\Models;

use App\Enums\TaskFrequency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Number;

class RecurringTask extends Model
{
    protected $fillable = [
        'user_id',
        'client_id',
        'title',
        'description',
        'frequency',
        'next_due_at',
        'last_run_at',
        'started_at',
        'ends_at',
        'amount',
        'billing_notes',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'frequency' => TaskFrequency::class,
            'next_due_at' => 'date',
            'last_run_at' => 'date',
            'started_at' => 'date',
            'ends_at' => 'date',
            'amount' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    /**
     * Global scope to ensure users only see their own recurring tasks.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('user', function (Builder $builder) {
            if (auth()->check()) {
                $builder->where('user_id', auth()->id());
            }
        });
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(RecurringTaskLog::class);
    }

    public function reminders(): MorphMany
    {
        return $this->morphMany(Reminder::class, 'remindable');
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('active', false);
    }

    public function scopeDueSoon(Builder $query, int $days = 7): Builder
    {
        return $query
            ->active()
            ->where('next_due_at', '<=', now()->addDays($days))
            ->where(function ($q) {
                $q->whereNull('ends_at')
                  ->orWhere('ends_at', '>=', now());
            });
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->active()
            ->where('next_due_at', '<', now()->startOfDay());
    }

    // Attributes

    public function getIsOverdueAttribute(): bool
    {
        return $this->active && $this->next_due_at < now()->startOfDay();
    }

    public function getIsDueSoonAttribute(): bool
    {
        $threshold = $this->frequency->daysBefore();
        return $this->active && $this->next_due_at <= now()->addDays($threshold);
    }

    public function getHasEndedAttribute(): bool
    {
        return $this->ends_at && $this->ends_at < now();
    }

    public function getFormattedAmountAttribute(): ?string
    {
        return $this->amount
            ? Number::currency($this->amount, 'EUR', 'de_DE')
            : null;
    }

    // Actions

    public function advance(): void
    {
        $this->update([
            'last_run_at' => $this->next_due_at,
            'next_due_at' => $this->frequency->nextDueDate($this->next_due_at),
        ]);

        // Deactivate if past end date
        if ($this->has_ended) {
            $this->update(['active' => false]);
        }
    }

    public function pause(): void
    {
        $this->update(['active' => false]);
    }

    public function resume(): void
    {
        // Adjust next_due_at if it's in the past
        $nextDue = $this->next_due_at;
        while ($nextDue < now()) {
            $nextDue = $this->frequency->nextDueDate($nextDue);
        }

        $this->update([
            'active' => true,
            'next_due_at' => $nextDue,
        ]);
    }
}
```

### RecurringTaskLog

```php
namespace App\Models;

class RecurringTaskLog extends Model
{
    protected $fillable = [
        'recurring_task_id',
        'due_date',
        'action',
        'reminder_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
        ];
    }

    public function recurringTask(): BelongsTo
    {
        return $this->belongsTo(RecurringTask::class);
    }

    public function reminder(): BelongsTo
    {
        return $this->belongsTo(Reminder::class);
    }
}
```

---

## Service Layer

### RecurringTaskService

```php
namespace App\Services;

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

        // Eager load client to prevent N+1 queries
        $dueTasks = RecurringTask::query()
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
        $reminder = $this->reminderService->createForEntity(
            entity: $task,
            title: $task->title,
            dueAt: $task->next_due_at->startOfDay()->setHour(9),
            description: $this->buildReminderDescription($task),
            priority: ReminderPriority::Normal
        );

        // Log the execution
        RecurringTaskLog::create([
            'recurring_task_id' => $task->id,
            'due_date' => $task->next_due_at,
            'action' => 'reminder_created',
            'reminder_id' => $reminder->id,
        ]);

        // Advance to next occurrence
        $task->advance();

        return $reminder;
    }

    /**
     * Create upcoming reminders for tasks due soon.
     * Called by scheduler to create reminders before due date.
     */
    public function createUpcomingReminders(): int
    {
        $created = 0;

        // Eager load client to prevent N+1 queries
        $tasks = RecurringTask::query()
            ->with('client')
            ->active()
            ->whereDoesntHave('reminders', function ($q) {
                $q->pending();
            })
            ->get()
            ->filter(fn ($task) => $task->is_due_soon);

        foreach ($tasks as $task) {
            $daysBeforeDue = $task->frequency->daysBefore();

            $this->reminderService->createForEntity(
                entity: $task,
                title: "Anstehend: {$task->title}",
                dueAt: $task->next_due_at->copy()->subDays($daysBeforeDue)->startOfDay()->setHour(9),
                description: $this->buildReminderDescription($task),
                priority: ReminderPriority::Normal
            );

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

        $parts[] = "Frequenz: {$task->frequency->label()}";
        $parts[] = "Fällig: {$task->next_due_at->format('d.m.Y')}";

        if ($task->amount) {
            $parts[] = "Betrag: {$task->formatted_amount}";
        }

        if ($task->description) {
            $parts[] = "";
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
    }
}
```

---

## Filament Resource

### RecurringTaskResource

```php
namespace App\Filament\Resources;

class RecurringTaskResource extends Resource
{
    protected static ?string $model = RecurringTask::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';
    protected static ?string $navigationLabel = 'Wiederkehrende Aufgaben';
    protected static ?string $modelLabel = 'Wiederkehrende Aufgabe';
    protected static ?string $pluralModelLabel = 'Wiederkehrende Aufgaben';
    protected static ?int $navigationSort = 6;

    /**
     * Show badge with count of overdue tasks in navigation.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()->overdue()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Aufgabe')
                ->schema([
                    TextInput::make('title')
                        ->label('Titel')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('z.B. Website-Wartung, Hosting-Verlängerung'),

                    Textarea::make('description')
                        ->label('Beschreibung')
                        ->rows(3)
                        ->placeholder('Details zur Aufgabe...'),

                    Select::make('client_id')
                        ->label('Kunde')
                        ->relationship('client', 'company_name')
                        ->getOptionLabelFromRecordUsing(fn (Client $record) => $record->display_name)
                        ->searchable()
                        ->preload()
                        ->placeholder('Kein Kunde zugeordnet'),
                ]),

            Section::make('Zeitplan')
                ->columns(2)
                ->schema([
                    Select::make('frequency')
                        ->label('Frequenz')
                        ->options(TaskFrequency::class)
                        ->required()
                        ->default(TaskFrequency::Monthly),

                    DatePicker::make('next_due_at')
                        ->label('Nächste Fälligkeit')
                        ->required()
                        ->native(false)
                        ->displayFormat('d.m.Y')
                        ->default(now()->addMonth()),

                    DatePicker::make('started_at')
                        ->label('Vertragsbeginn')
                        ->native(false)
                        ->displayFormat('d.m.Y'),

                    DatePicker::make('ends_at')
                        ->label('Vertragsende')
                        ->native(false)
                        ->displayFormat('d.m.Y')
                        ->helperText('Leer lassen für unbefristete Aufgaben'),
                ]),

            Section::make('Abrechnung')
                ->columns(2)
                ->schema([
                    TextInput::make('amount')
                        ->label('Betrag')
                        ->numeric()
                        ->prefix('€')
                        ->placeholder('0,00'),

                    TextInput::make('billing_notes')
                        ->label('Abrechnungsnotizen')
                        ->placeholder('z.B. Monatliche Pauschale'),
                ]),

            Section::make('Status')
                ->schema([
                    Toggle::make('active')
                        ->label('Aktiv')
                        ->default(true)
                        ->helperText('Inaktive Aufgaben werden nicht verarbeitet'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Aufgabe')
                    ->searchable()
                    ->sortable()
                    ->description(fn (RecurringTask $record) => $record->client?->display_name),

                TextColumn::make('frequency')
                    ->label('Frequenz')
                    ->badge(),

                TextColumn::make('next_due_at')
                    ->label('Nächste Fälligkeit')
                    ->date('d.m.Y')
                    ->sortable()
                    ->color(fn (RecurringTask $record) => match(true) {
                        $record->is_overdue => 'danger',
                        $record->is_due_soon => 'warning',
                        default => null,
                    }),

                TextColumn::make('amount')
                    ->label('Betrag')
                    ->money('EUR', locale: 'de')
                    ->placeholder('-'),

                IconColumn::make('active')
                    ->label('Aktiv')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('frequency')
                    ->options(TaskFrequency::class),

                SelectFilter::make('client')
                    ->relationship('client', 'company_name'),

                TernaryFilter::make('active')
                    ->label('Status')
                    ->placeholder('Alle')
                    ->trueLabel('Nur aktive')
                    ->falseLabel('Nur inaktive'),
            ])
            ->actions([
                Action::make('process')
                    ->label('Jetzt ausführen')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn (RecurringTask $record) => $record->active && $record->is_overdue)
                    ->requiresConfirmation()
                    ->action(function (RecurringTask $record) {
                        app(RecurringTaskService::class)->processTask($record);

                        Notification::make()
                            ->title('Aufgabe verarbeitet')
                            ->body('Erinnerung wurde erstellt und Aufgabe weitergeschaltet.')
                            ->success()
                            ->send();
                    }),

                Action::make('skip')
                    ->label('Überspringen')
                    ->icon('heroicon-o-forward')
                    ->color('warning')
                    ->visible(fn (RecurringTask $record) => $record->active)
                    ->form([
                        Textarea::make('reason')
                            ->label('Grund (optional)')
                            ->rows(2),
                    ])
                    ->action(function (RecurringTask $record, array $data) {
                        app(RecurringTaskService::class)->skipOccurrence($record, $data['reason'] ?? null);

                        Notification::make()
                            ->title('Aufgabe übersprungen')
                            ->success()
                            ->send();
                    }),

                Action::make('toggleActive')
                    ->label(fn (RecurringTask $record) => $record->active ? 'Pausieren' : 'Fortsetzen')
                    ->icon(fn (RecurringTask $record) => $record->active ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->action(function (RecurringTask $record) {
                        $record->active ? $record->pause() : $record->resume();
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('next_due_at', 'asc');
    }

    public static function getRelations(): array
    {
        return [
            LogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRecurringTasks::route('/'),
            'create' => Pages\CreateRecurringTask::route('/create'),
            'edit' => Pages\EditRecurringTask::route('/{record}/edit'),
        ];
    }
}
```

### LogsRelationManager

```php
namespace App\Filament\Resources\RecurringTaskResource\RelationManagers;

class LogsRelationManager extends RelationManager
{
    protected static string $relationship = 'logs';
    protected static ?string $title = 'Verlauf';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('due_date')
                    ->label('Fälligkeitsdatum')
                    ->date('d.m.Y'),

                TextColumn::make('action')
                    ->label('Aktion')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match($state) {
                        'reminder_created' => 'Erinnerung erstellt',
                        'manually_completed' => 'Manuell erledigt',
                        'skipped' => 'Übersprungen',
                        default => $state,
                    })
                    ->color(fn (string $state) => match($state) {
                        'reminder_created' => 'success',
                        'skipped' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('notes')
                    ->label('Notizen')
                    ->placeholder('-')
                    ->limit(50),

                TextColumn::make('created_at')
                    ->label('Datum')
                    ->dateTime('d.m.Y H:i'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
```

---

## Scheduled Commands

```php
// In routes/console.php

use App\Services\RecurringTaskService;
use Illuminate\Support\Facades\Schedule;

// Process due tasks daily at 8am
Schedule::call(function () {
    $service = app(RecurringTaskService::class);
    $processed = $service->processDueTasks();

    Log::info("Processed {$processed} recurring tasks");
})->daily()->at('08:00');

// Create upcoming reminders daily at 7am
Schedule::call(function () {
    $service = app(RecurringTaskService::class);
    $created = $service->createUpcomingReminders();

    Log::info("Created {$created} upcoming task reminders");
})->daily()->at('07:00');
```

---

## Client Resource Integration

Add recurring tasks to ClientResource:

```php
// In ClientResource::getRelations()
RecurringTasksRelationManager::class,
```

```php
namespace App\Filament\Resources\ClientResource\RelationManagers;

class RecurringTasksRelationManager extends RelationManager
{
    protected static string $relationship = 'recurringTasks';
    protected static ?string $title = 'Wiederkehrende Aufgaben';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Aufgabe'),
                TextColumn::make('frequency')
                    ->label('Frequenz')
                    ->badge(),
                TextColumn::make('next_due_at')
                    ->label('Nächste Fälligkeit')
                    ->date('d.m.Y'),
                TextColumn::make('amount')
                    ->label('Betrag')
                    ->money('EUR', locale: 'de'),
                IconColumn::make('active')
                    ->label('Aktiv')
                    ->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public function form(Form $form): Form
    {
        // Simplified form for relation manager
        return $form->schema([
            TextInput::make('title')->required(),
            Select::make('frequency')
                ->options(TaskFrequency::class)
                ->required(),
            DatePicker::make('next_due_at')
                ->required()
                ->native(false),
            TextInput::make('amount')
                ->numeric()
                ->prefix('€'),
            Toggle::make('active')->default(true),
        ]);
    }
}
```

---

## Testing

```php
// tests/Feature/RecurringTaskTest.php

it('advances task to next due date after processing', function () {
    $task = RecurringTask::factory()->create([
        'frequency' => TaskFrequency::Monthly,
        'next_due_at' => now()->subDay(),
    ]);

    app(RecurringTaskService::class)->processTask($task);

    $task->refresh();

    expect($task->last_run_at->toDateString())->toBe(now()->subDay()->toDateString());
    expect($task->next_due_at->toDateString())->toBe(now()->subDay()->addMonth()->toDateString());
});

it('creates reminder when processing task', function () {
    $task = RecurringTask::factory()->create([
        'next_due_at' => now(),
    ]);

    $reminder = app(RecurringTaskService::class)->processTask($task);

    expect($reminder)->toBeInstanceOf(Reminder::class);
    expect($reminder->title)->toBe($task->title);
});

it('deactivates task after end date', function () {
    $task = RecurringTask::factory()->create([
        'frequency' => TaskFrequency::Monthly,
        'next_due_at' => now()->subDay(),
        'ends_at' => now()->subDay(),
    ]);

    app(RecurringTaskService::class)->processTask($task);

    expect($task->refresh()->active)->toBeFalse();
});

it('adjusts next due date when resuming paused task', function () {
    $task = RecurringTask::factory()->create([
        'frequency' => TaskFrequency::Monthly,
        'next_due_at' => now()->subMonths(2),
        'active' => false,
    ]);

    $task->resume();

    expect($task->next_due_at->isFuture())->toBeTrue();
});
```

---

## Migration Path

1. Create `recurring_tasks` migration (if not exists) and `recurring_task_logs` migration
2. Create TaskFrequency enum (with Filament contracts)
3. Create RecurringTask model with global user scope
4. Create RecurringTaskLog model
5. Create RecurringTaskFactory and RecurringTaskLogFactory for testing
6. Create RecurringTaskService with eager loading
7. Create RecurringTaskResource with full CRUD and navigation badge
8. Add LogsRelationManager for task history
9. Add RecurringTasksRelationManager to ClientResource
10. Add `recurringTasks()` HasMany relation to User and Client models
11. Add scheduled commands for processing
12. Write tests (unit + feature + scheduler tests)
