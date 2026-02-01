# Reminder System

## Overview

A polymorphic reminder system that can be attached to any entity (Client, Project, Invoice) with automatic notifications and dashboard integration. Helps ensure follow-ups never fall through the cracks.

## Features

| Feature | Description | Priority |
|---------|-------------|----------|
| Reminder Model | Polymorphic reminders for any entity | P0 |
| ReminderResource | Full CRUD via Filament | P0 |
| Dashboard Widget | Upcoming reminders on dashboard | P0 |
| Filament Notifications | In-app notifications for due reminders | P1 |
| Auto-Reminders | Auto-create for overdue invoices | P1 |
| Quick Create | Add reminder from entity pages | P2 |
| Recurrence | Repeating reminders (daily/weekly/monthly) | P2 |

---

## Data Model

### reminders

Already defined in `02-data-model.md`, implementation:

```php
Schema::create('reminders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();

    // Polymorphic relation (Client, Project, Invoice, or null for standalone)
    $table->nullableMorphs('remindable');

    $table->string('title');
    $table->text('description')->nullable();
    $table->timestamp('due_at');
    $table->timestamp('snoozed_until')->nullable();
    $table->timestamp('completed_at')->nullable();

    // Recurrence (null = one-time)
    $table->enum('recurrence', ['daily', 'weekly', 'monthly', 'yearly'])->nullable();

    // Priority for sorting
    $table->enum('priority', ['low', 'normal', 'high'])->default('normal');

    // Auto-generated reminders
    $table->boolean('is_system')->default(false);
    $table->string('system_type')->nullable(); // 'overdue_invoice', 'offer_followup', etc.

    $table->timestamps();

    $table->index(['user_id', 'due_at']);
    $table->index(['user_id', 'completed_at', 'due_at']);
    $table->index(['remindable_type', 'remindable_id']);
});
```

---

## Enums

**Important:** All enums must implement Filament's `HasLabel` and `HasColor` contracts for proper badge rendering.

### ReminderPriority

```php
namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ReminderPriority: string implements HasLabel, HasColor, HasIcon
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';

    public function getLabel(): string
    {
        return match ($this) {
            self::Low => 'Niedrig',
            self::Normal => 'Normal',
            self::High => 'Hoch',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Low => 'gray',
            self::Normal => 'info',
            self::High => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Low => 'heroicon-o-arrow-down',
            self::Normal => 'heroicon-o-minus',
            self::High => 'heroicon-o-arrow-up',
        };
    }
}
```

### ReminderRecurrence

```php
namespace App\Enums;

use Carbon\Carbon;
use Filament\Support\Contracts\HasLabel;

enum ReminderRecurrence: string implements HasLabel
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function getLabel(): string
    {
        return match ($this) {
            self::Daily => 'Täglich',
            self::Weekly => 'Wöchentlich',
            self::Monthly => 'Monatlich',
            self::Yearly => 'Jährlich',
        };
    }

    public function nextDueDate(Carbon $from): Carbon
    {
        return match ($this) {
            self::Daily => $from->copy()->addDay(),
            self::Weekly => $from->copy()->addWeek(),
            self::Monthly => $from->copy()->addMonth(),
            self::Yearly => $from->copy()->addYear(),
        };
    }
}
```

---

## Model

### Reminder

```php
namespace App\Models;

use App\Enums\ReminderPriority;
use App\Enums\ReminderRecurrence;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Reminder extends Model
{
    protected $fillable = [
        'user_id',
        'remindable_type',
        'remindable_id',
        'title',
        'description',
        'due_at',
        'snoozed_until',
        'completed_at',
        'recurrence',
        'priority',
        'is_system',
        'system_type',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'snoozed_until' => 'datetime',
            'completed_at' => 'datetime',
            'recurrence' => ReminderRecurrence::class,
            'priority' => ReminderPriority::class,
            'is_system' => 'boolean',
        ];
    }

    /**
     * Global scope to ensure users only see their own reminders.
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

    public function remindable(): MorphTo
    {
        return $this->morphTo();
    }

    // Scopes

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('completed_at');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereNotNull('completed_at');
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query
            ->pending()
            ->where(function ($q) {
                $q->whereNull('snoozed_until')
                  ->orWhere('snoozed_until', '<=', now());
            })
            ->where('due_at', '<=', now());
    }

    public function scopeUpcoming(Builder $query, int $days = 7): Builder
    {
        return $query
            ->pending()
            ->where('due_at', '<=', now()->addDays($days))
            ->orderBy('due_at');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->pending()
            ->where('due_at', '<', now()->startOfDay());
    }

    // Attributes

    public function getIsOverdueAttribute(): bool
    {
        return $this->due_at < now()->startOfDay() && !$this->completed_at;
    }

    public function getIsDueTodayAttribute(): bool
    {
        return $this->due_at->isToday() && !$this->completed_at;
    }

    public function getEffectiveDueAtAttribute(): Carbon
    {
        return $this->snoozed_until ?? $this->due_at;
    }

    // Actions

    public function complete(): void
    {
        if ($this->recurrence) {
            // Create next occurrence
            self::create([
                'user_id' => $this->user_id,
                'remindable_type' => $this->remindable_type,
                'remindable_id' => $this->remindable_id,
                'title' => $this->title,
                'description' => $this->description,
                'due_at' => $this->recurrence->nextDueDate($this->due_at),
                'recurrence' => $this->recurrence,
                'priority' => $this->priority,
            ]);
        }

        $this->update(['completed_at' => now()]);
    }

    public function snooze(int $hours = 24): void
    {
        $this->update(['snoozed_until' => now()->addHours($hours)]);
    }

    public function reopen(): void
    {
        $this->update([
            'completed_at' => null,
            'snoozed_until' => null,
        ]);
    }
}
```

### Add to Existing Models

```php
// In Client, Project, Invoice models:

public function reminders(): MorphMany
{
    return $this->morphMany(Reminder::class, 'remindable');
}
```

---

## Service Layer

### ReminderService

```php
namespace App\Services;

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
        $existing = Reminder::where('remindable_type', Invoice::class)
            ->where('remindable_id', $invoice->id)
            ->where('system_type', 'overdue_invoice')
            ->pending()
            ->first();

        if ($existing) {
            return $existing;
        }

        return Reminder::create([
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
        $existing = Reminder::where('remindable_type', Project::class)
            ->where('remindable_id', $project->id)
            ->where('system_type', 'offer_followup')
            ->pending()
            ->first();

        if ($existing) {
            return $existing;
        }

        return Reminder::create([
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

    /**
     * Process due reminders and send notifications.
     */
    public function processDueReminders(): int
    {
        $dueReminders = Reminder::due()
            ->whereNull('notified_at') // Add this column if tracking notifications
            ->get();

        foreach ($dueReminders as $reminder) {
            $reminder->user->notify(new ReminderDueNotification($reminder));
            // Or use Filament notifications if preferred
        }

        return $dueReminders->count();
    }
}
```

---

## Filament Resource

### ReminderResource

```php
namespace App\Filament\Resources;

class ReminderResource extends Resource
{
    protected static ?string $model = Reminder::class;
    protected static ?string $navigationIcon = 'heroicon-o-bell';
    protected static ?string $navigationLabel = 'Erinnerungen';
    protected static ?string $modelLabel = 'Erinnerung';
    protected static ?string $pluralModelLabel = 'Erinnerungen';
    protected static ?int $navigationSort = 5;

    /**
     * Show badge with count of overdue reminders in navigation.
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
            Section::make()
                ->schema([
                    TextInput::make('title')
                        ->label('Titel')
                        ->required()
                        ->maxLength(255),

                    Textarea::make('description')
                        ->label('Beschreibung')
                        ->rows(3),

                    DateTimePicker::make('due_at')
                        ->label('Fällig am')
                        ->required()
                        ->native(false)
                        ->displayFormat('d.m.Y H:i'),

                    Select::make('priority')
                        ->label('Priorität')
                        ->options(ReminderPriority::class)
                        ->default(ReminderPriority::Normal),

                    Select::make('recurrence')
                        ->label('Wiederholung')
                        ->options(ReminderRecurrence::class)
                        ->placeholder('Keine Wiederholung'),

                    MorphToSelect::make('remindable')
                        ->label('Verknüpft mit')
                        ->types([
                            MorphToSelect\Type::make(Client::class)
                                ->titleAttribute('display_name')
                                ->label('Kunde'),
                            MorphToSelect\Type::make(Project::class)
                                ->titleAttribute('title')
                                ->label('Projekt'),
                            MorphToSelect\Type::make(Invoice::class)
                                ->titleAttribute('number')
                                ->label('Rechnung'),
                        ])
                        ->searchable()
                        ->preload(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Titel')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Reminder $record) => $record->remindable?->display_name ?? $record->remindable?->title ?? $record->remindable?->number),

                TextColumn::make('due_at')
                    ->label('Fällig')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->color(fn (Reminder $record) => $record->is_overdue ? 'danger' : null),

                TextColumn::make('priority')
                    ->label('Priorität')
                    ->badge(),

                TextColumn::make('recurrence')
                    ->label('Wiederholung')
                    ->placeholder('Einmalig'),

                // Visual distinction for system-generated reminders
                IconColumn::make('is_system')
                    ->label('Typ')
                    ->boolean()
                    ->trueIcon('heroicon-o-cog-6-tooth')
                    ->falseIcon('heroicon-o-user')
                    ->trueColor('gray')
                    ->falseColor('primary')
                    ->tooltip(fn (Reminder $record) => $record->is_system ? 'Automatisch erstellt' : 'Manuell erstellt'),

                IconColumn::make('completed_at')
                    ->label('Erledigt')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Ausstehend',
                        'completed' => 'Erledigt',
                        'overdue' => 'Überfällig',
                    ])
                    ->query(function (Builder $query, array $data) {
                        return match ($data['value']) {
                            'pending' => $query->pending(),
                            'completed' => $query->completed(),
                            'overdue' => $query->overdue(),
                            default => $query,
                        };
                    }),

                SelectFilter::make('priority')
                    ->options(ReminderPriority::class),
            ])
            ->actions([
                Action::make('complete')
                    ->label('Erledigt')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Reminder $record) => !$record->completed_at)
                    ->action(fn (Reminder $record) => $record->complete()),

                Action::make('snooze')
                    ->label('Später')
                    ->icon('heroicon-o-clock')
                    ->visible(fn (Reminder $record) => !$record->completed_at)
                    ->form([
                        Select::make('hours')
                            ->label('Erinnere mich in')
                            ->options([
                                1 => '1 Stunde',
                                4 => '4 Stunden',
                                24 => '1 Tag',
                                72 => '3 Tage',
                                168 => '1 Woche',
                            ])
                            ->default(24),
                    ])
                    ->action(fn (Reminder $record, array $data) => $record->snooze($data['hours'])),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('due_at', 'asc')
            ->modifyQueryUsing(fn (Builder $query) => $query->pending());
    }
}
```

---

## Dashboard Widget

### UpcomingRemindersWidget

```php
namespace App\Filament\Widgets;

use App\Filament\Resources\ReminderResource;
use App\Models\Reminder;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class UpcomingRemindersWidget extends Widget
{
    protected static string $view = 'filament.widgets.upcoming-reminders';
    protected int | string | array $columnSpan = 'full';
    protected static ?int $sort = 2;

    public function getReminders(): Collection
    {
        // Global scope handles user_id filtering automatically
        return Reminder::query()
            ->upcoming(7)
            ->with('remindable')
            ->limit(5)
            ->get();
    }

    /**
     * Complete a reminder with proper authorization check.
     */
    public function completeReminder(int $id): void
    {
        // Global scope ensures user can only access their own reminders
        $reminder = Reminder::findOrFail($id);
        $reminder->complete();

        Notification::make()
            ->title('Erinnerung erledigt')
            ->success()
            ->send();
    }

    /**
     * Snooze a reminder with proper authorization check.
     */
    public function snoozeReminder(int $id, int $hours = 24): void
    {
        // Global scope ensures user can only access their own reminders
        $reminder = Reminder::findOrFail($id);
        $reminder->snooze($hours);

        Notification::make()
            ->title('Erinnerung verschoben')
            ->success()
            ->send();
    }

    /**
     * Check if there are any reminders to display.
     */
    public function hasReminders(): bool
    {
        return $this->getReminders()->isNotEmpty();
    }
}
```

### Widget Blade View

```blade
{{-- resources/views/filament/widgets/upcoming-reminders.blade.php --}}
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-o-bell class="h-5 w-5" />
                Anstehende Erinnerungen
            </div>
        </x-slot>

        @if($this->getReminders()->isEmpty())
            {{-- Empty state with CTA --}}
            <div class="text-center py-4">
                <x-heroicon-o-bell-slash class="mx-auto h-8 w-8 text-gray-400" />
                <p class="mt-2 text-sm text-gray-500">Keine anstehenden Erinnerungen.</p>
                <x-filament::button
                    :href="\App\Filament\Resources\ReminderResource::getUrl('create')"
                    tag="a"
                    size="sm"
                    color="gray"
                    class="mt-3"
                >
                    Erste Erinnerung erstellen
                </x-filament::button>
            </div>
        @else
            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($this->getReminders() as $reminder)
                    <li class="py-3 flex items-center justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-medium truncate {{ $reminder->is_overdue ? 'text-danger-600' : '' }}">
                                    {{ $reminder->title }}
                                </p>
                                {{-- Visual distinction for system-generated reminders --}}
                                @if($reminder->is_system)
                                    <x-filament::badge size="sm" color="gray">
                                        Auto
                                    </x-filament::badge>
                                @endif
                            </div>
                            <p class="text-xs text-gray-500">
                                {{ $reminder->due_at->format('d.m.Y H:i') }}
                                @if($reminder->remindable)
                                    · {{ class_basename($reminder->remindable) }}
                                @endif
                            </p>
                        </div>
                        <div class="flex gap-1">
                            <x-filament::icon-button
                                icon="heroicon-o-check"
                                color="success"
                                size="sm"
                                wire:click="completeReminder({{ $reminder->id }})"
                                tooltip="Erledigt"
                            />
                            <x-filament::icon-button
                                icon="heroicon-o-clock"
                                color="gray"
                                size="sm"
                                wire:click="snoozeReminder({{ $reminder->id }})"
                                tooltip="Später erinnern"
                            />
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="mt-3">
                <x-filament::link :href="\App\Filament\Resources\ReminderResource::getUrl()">
                    Alle Erinnerungen anzeigen →
                </x-filament::link>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
```

---

## Relation Managers

Add RemindersRelationManager to Client, Project, and Invoice resources:

```php
namespace App\Filament\Resources\ClientResource\RelationManagers;

class RemindersRelationManager extends RelationManager
{
    protected static string $relationship = 'reminders';
    protected static ?string $title = 'Erinnerungen';

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('title')
                ->label('Titel')
                ->required(),

            DateTimePicker::make('due_at')
                ->label('Fällig am')
                ->required()
                ->native(false),

            Textarea::make('description')
                ->label('Beschreibung'),

            Select::make('priority')
                ->label('Priorität')
                ->options(ReminderPriority::class)
                ->default(ReminderPriority::Normal),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Titel'),
                TextColumn::make('due_at')
                    ->label('Fällig')
                    ->dateTime('d.m.Y H:i')
                    ->color(fn (Reminder $record) => $record->is_overdue ? 'danger' : null),
                TextColumn::make('priority')
                    ->label('Priorität')
                    ->badge(),
                IconColumn::make('completed_at')
                    ->label('Erledigt')
                    ->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Action::make('complete')
                    ->icon('heroicon-o-check')
                    ->visible(fn (Reminder $record) => !$record->completed_at)
                    ->action(fn (Reminder $record) => $record->complete()),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->pending());
    }
}
```

---

## Scheduled Commands

### CheckOverdueInvoices

```php
// In routes/console.php or as a Command class

use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    $overdueInvoices = Invoice::query()
        ->where('status', InvoiceStatus::Sent)
        ->where('due_at', '<', now()->startOfDay())
        ->get();

    $reminderService = app(ReminderService::class);

    foreach ($overdueInvoices as $invoice) {
        $reminderService->createOverdueInvoiceReminder($invoice);
    }
})->daily()->at('08:00');
```

---

## Testing

```php
// tests/Feature/ReminderTest.php

it('can create a reminder for a client', function () {
    $user = User::factory()->create();
    $client = Client::factory()->for($user)->create();

    $this->actingAs($user);

    $reminder = app(ReminderService::class)->createForEntity(
        $client,
        'Follow up on proposal',
        now()->addDays(7)
    );

    expect($reminder->remindable)->toBeInstanceOf(Client::class);
    expect($reminder->remindable->id)->toBe($client->id);
});

it('creates next occurrence when completing recurring reminder', function () {
    $reminder = Reminder::factory()->create([
        'recurrence' => ReminderRecurrence::Weekly,
        'due_at' => now(),
    ]);

    $reminder->complete();

    $newReminder = Reminder::pending()->latest()->first();

    expect($newReminder->due_at->toDateString())
        ->toBe(now()->addWeek()->toDateString());
});

it('auto-creates reminder for overdue invoice', function () {
    // Test scheduled job creates reminder
});
```

---

## Migration Path

1. Create `reminders` migration
2. Create Reminder model with global user scope, scopes, and actions
3. Create ReminderFactory for testing
4. Create ReminderPriority and ReminderRecurrence enums (with Filament contracts)
5. Create ReminderService
6. Create ReminderResource with CRUD and system reminder distinction
7. Create UpcomingRemindersWidget with empty state CTA
8. Add `reminders()` MorphMany relation to Client, Project, Invoice models
9. Add RemindersRelationManager to Client, Project, Invoice resources
10. Add scheduled command for overdue invoice reminders
11. Write tests (unit + feature + scheduled command)
