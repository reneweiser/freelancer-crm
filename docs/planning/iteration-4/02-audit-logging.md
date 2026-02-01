# GoBD-Compliant Audit Logging

## Overview

German GoBD (Grundsätze zur ordnungsmäßigen Führung und Aufbewahrung von Büchern) requires all changes to financial documents be tracked with full audit trail. This document specifies the audit logging implementation.

## GoBD Requirements

1. **Immutability** - Original data must be preserved; changes create new versions
2. **Traceability** - Every change must be traceable to a user and timestamp
3. **Completeness** - No gaps in the audit trail; all operations logged
4. **Verifiability** - Auditors must be able to reconstruct document history

## Package Selection

**spatie/laravel-activitylog** (v4.x)

- Proven, well-maintained package
- Stores old/new values automatically
- User attribution built-in
- Custom properties support
- Query builder for log retrieval

### Installation

```bash
sail composer require spatie/laravel-activitylog
sail artisan vendor:publish --tag=activitylog-migrations
sail artisan migrate
```

## Configuration

```php
// config/activitylog.php

return [
    'enabled' => true,
    'delete_records_older_than_days' => 365 * 10, // 10 years for GoBD
    'default_log_name' => 'default',
    'default_auth_driver' => null,
    'subject_returns_soft_deleted_models' => true,
    'activity_model' => \Spatie\Activitylog\Models\Activity::class,
    'table_name' => 'activity_log',
    'database_connection' => null,
];
```

## Model Integration

### Invoice Model

```php
namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Invoice extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'number',
                'status',
                'client_id',
                'project_id',
                'issued_at',
                'due_at',
                'paid_at',
                'payment_method',
                'subtotal',
                'vat_rate',
                'vat_scheme',
                'vat_amount',
                'total',
                'service_period_start',
                'service_period_end',
                'service_date',
                'notes',
                'footer_text',
                'legal_notice',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => match($eventName) {
                'created' => 'Rechnung erstellt',
                'updated' => 'Rechnung bearbeitet',
                'deleted' => 'Rechnung gelöscht',
                default => "Rechnung {$eventName}",
            });
    }

    /**
     * Log status change with additional context
     */
    public function logStatusChange(InvoiceStatus $oldStatus, InvoiceStatus $newStatus): void
    {
        activity()
            ->performedOn($this)
            ->withProperties([
                'old_status' => $oldStatus->value,
                'new_status' => $newStatus->value,
                'old_status_label' => $oldStatus->getLabel(),
                'new_status_label' => $newStatus->getLabel(),
            ])
            ->log("Status geändert: {$oldStatus->getLabel()} → {$newStatus->getLabel()}");
    }

    /**
     * Log payment receipt
     */
    public function logPayment(float $amount, string $method): void
    {
        activity()
            ->performedOn($this)
            ->withProperties([
                'amount' => $amount,
                'payment_method' => $method,
                'paid_at' => now()->toIso8601String(),
            ])
            ->log("Zahlung erhalten: {$amount} € via {$method}");
    }
}
```

### InvoiceItem Model

```php
namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class InvoiceItem extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'description',
                'quantity',
                'unit',
                'unit_price',
                'vat_rate',
                'position',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('invoice_items')
            ->setDescriptionForEvent(fn(string $eventName) => match($eventName) {
                'created' => 'Position hinzugefügt',
                'updated' => 'Position bearbeitet',
                'deleted' => 'Position entfernt',
                default => "Position {$eventName}",
            });
    }

    /**
     * Include parent invoice in log context
     */
    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->properties = $activity->properties->merge([
            'invoice_id' => $this->invoice_id,
            'invoice_number' => $this->invoice?->number,
        ]);
    }
}
```

### Expense Model (New)

```php
namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Expense extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'supplier_id',
                'category',
                'description',
                'amount',
                'vat_amount',
                'vat_rate',
                'date',
                'receipt_path',
                'payment_method',
                'payment_date',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('expenses')
            ->setDescriptionForEvent(fn(string $eventName) => match($eventName) {
                'created' => 'Ausgabe erfasst',
                'updated' => 'Ausgabe bearbeitet',
                'deleted' => 'Ausgabe gelöscht',
                default => "Ausgabe {$eventName}",
            });
    }
}
```

## Activity Log Schema Extension

Create a custom migration to add GoBD-specific fields:

```php
// database/migrations/2026_XX_XX_add_gobd_fields_to_activity_log.php

Schema::table('activity_log', function (Blueprint $table) {
    // Checksum for integrity verification
    $table->string('checksum')->nullable()->after('properties');

    // IP address for audit trail
    $table->string('ip_address')->nullable()->after('checksum');

    // User agent for context
    $table->string('user_agent')->nullable()->after('ip_address');

    // Index for efficient querying
    $table->index(['subject_type', 'subject_id', 'created_at']);
    $table->index(['causer_id', 'created_at']);
});
```

## Checksum Generation

```php
namespace App\Services;

class AuditChecksumService
{
    /**
     * Generate a checksum for an activity log entry
     */
    public function generateChecksum(Activity $activity): string
    {
        $data = [
            'id' => $activity->id,
            'log_name' => $activity->log_name,
            'description' => $activity->description,
            'subject_type' => $activity->subject_type,
            'subject_id' => $activity->subject_id,
            'causer_type' => $activity->causer_type,
            'causer_id' => $activity->causer_id,
            'properties' => $activity->properties->toJson(),
            'created_at' => $activity->created_at->toIso8601String(),
        ];

        return hash('sha256', json_encode($data));
    }

    /**
     * Verify integrity of an activity log entry
     */
    public function verifyChecksum(Activity $activity): bool
    {
        $expected = $this->generateChecksum($activity);
        return hash_equals($activity->checksum, $expected);
    }
}
```

## Event Listener for Checksum

```php
namespace App\Listeners;

use Spatie\Activitylog\Models\Activity;

class AddAuditMetadata
{
    public function handle(Activity $activity): void
    {
        // Add IP address
        $activity->ip_address = request()->ip();

        // Add user agent
        $activity->user_agent = substr(request()->userAgent() ?? '', 0, 255);

        // Generate checksum
        $checksumService = app(AuditChecksumService::class);
        $activity->checksum = $checksumService->generateChecksum($activity);

        $activity->save();
    }
}
```

Register in EventServiceProvider:

```php
protected $listen = [
    \Spatie\Activitylog\Events\ActivityLogged::class => [
        \App\Listeners\AddAuditMetadata::class,
    ],
];
```

## Filament Integration

### Audit Log Relation Manager

```php
namespace App\Filament\Resources\Invoices\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Spatie\Activitylog\Models\Activity;

class AuditLogRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $title = 'Änderungsprotokoll';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Zeitpunkt')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Aktion')
                    ->searchable(),

                Tables\Columns\TextColumn::make('causer.name')
                    ->label('Benutzer')
                    ->default('System'),

                Tables\Columns\TextColumn::make('properties.old')
                    ->label('Alter Wert')
                    ->formatStateUsing(fn ($state) => $this->formatProperties($state))
                    ->wrap(),

                Tables\Columns\TextColumn::make('properties.attributes')
                    ->label('Neuer Wert')
                    ->formatStateUsing(fn ($state) => $this->formatProperties($state))
                    ->wrap(),

                Tables\Columns\IconColumn::make('checksum_valid')
                    ->label('Integrität')
                    ->boolean()
                    ->getStateUsing(fn (Activity $record) =>
                        app(AuditChecksumService::class)->verifyChecksum($record)
                    ),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    protected function formatProperties(?array $properties): string
    {
        if (empty($properties)) {
            return '-';
        }

        return collect($properties)
            ->map(fn ($value, $key) => "{$key}: {$value}")
            ->join("\n");
    }
}
```

### Standalone Audit Log Page

```php
namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Spatie\Activitylog\Models\Activity;

class AuditLog extends Page implements Tables\Contracts\HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-magnifying-glass';
    protected static ?string $navigationLabel = 'Audit-Protokoll';
    protected static ?string $navigationGroup = 'System';
    protected static ?int $navigationSort = 100;

    protected static string $view = 'filament.pages.audit-log';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Activity::query()
                    ->whereHas('causer', fn ($q) => $q->where('id', auth()->id()))
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Zeitpunkt')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('log_name')
                    ->label('Bereich')
                    ->badge(),

                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Objekt')
                    ->formatStateUsing(fn ($state) => class_basename($state)),

                Tables\Columns\TextColumn::make('description')
                    ->label('Aktion')
                    ->searchable(),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP-Adresse')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('log_name')
                    ->label('Bereich')
                    ->options([
                        'default' => 'Rechnungen',
                        'invoice_items' => 'Rechnungspositionen',
                        'expenses' => 'Ausgaben',
                    ]),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Von'),
                        Forms\Components\DatePicker::make('until')->label('Bis'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q) => $q->whereDate('created_at', '>=', $data['from']))
                            ->when($data['until'], fn ($q) => $q->whereDate('created_at', '<=', $data['until']));
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100]);
    }
}
```

## Export for Tax Auditors

```php
namespace App\Services;

use Spatie\Activitylog\Models\Activity;
use League\Csv\Writer;

class AuditExportService
{
    /**
     * Export audit log for a date range as CSV
     */
    public function exportToCsv(
        User $user,
        Carbon $from,
        Carbon $until,
        ?string $logName = null
    ): string {
        $activities = Activity::query()
            ->where('causer_id', $user->id)
            ->whereBetween('created_at', [$from, $until])
            ->when($logName, fn ($q) => $q->where('log_name', $logName))
            ->orderBy('created_at')
            ->get();

        $csv = Writer::createFromString();
        $csv->insertOne([
            'Zeitpunkt',
            'Bereich',
            'Objekt-Typ',
            'Objekt-ID',
            'Aktion',
            'Alte Werte',
            'Neue Werte',
            'IP-Adresse',
            'Prüfsumme',
            'Integrität OK',
        ]);

        foreach ($activities as $activity) {
            $csv->insertOne([
                $activity->created_at->format('Y-m-d H:i:s'),
                $activity->log_name,
                class_basename($activity->subject_type),
                $activity->subject_id,
                $activity->description,
                json_encode($activity->properties['old'] ?? [], JSON_UNESCAPED_UNICODE),
                json_encode($activity->properties['attributes'] ?? [], JSON_UNESCAPED_UNICODE),
                $activity->ip_address,
                $activity->checksum,
                app(AuditChecksumService::class)->verifyChecksum($activity) ? 'Ja' : 'FEHLER',
            ]);
        }

        return $csv->toString();
    }

    /**
     * Export as JSON for machine processing
     */
    public function exportToJson(
        User $user,
        Carbon $from,
        Carbon $until
    ): array {
        return Activity::query()
            ->where('causer_id', $user->id)
            ->whereBetween('created_at', [$from, $until])
            ->orderBy('created_at')
            ->get()
            ->map(fn ($activity) => [
                'timestamp' => $activity->created_at->toIso8601String(),
                'log_name' => $activity->log_name,
                'subject' => [
                    'type' => class_basename($activity->subject_type),
                    'id' => $activity->subject_id,
                ],
                'description' => $activity->description,
                'changes' => $activity->properties->toArray(),
                'metadata' => [
                    'ip_address' => $activity->ip_address,
                    'user_agent' => $activity->user_agent,
                    'checksum' => $activity->checksum,
                ],
            ])
            ->toArray();
    }
}
```

## Protection Against Tampering

### Read-Only Activity Model

```php
namespace App\Models;

use Spatie\Activitylog\Models\Activity as BaseActivity;

class Activity extends BaseActivity
{
    /**
     * Prevent updates to activity records (GoBD compliance)
     */
    public static function boot(): void
    {
        parent::boot();

        static::updating(function () {
            throw new \RuntimeException('Audit log entries cannot be modified (GoBD compliance)');
        });

        static::deleting(function () {
            throw new \RuntimeException('Audit log entries cannot be deleted (GoBD compliance)');
        });
    }
}
```

Update config to use custom model:

```php
// config/activitylog.php
'activity_model' => \App\Models\Activity::class,
```

## Test Cases

```php
// tests/Feature/AuditLogTest.php

it('logs invoice creation', function () {
    $invoice = Invoice::factory()->create();

    $activity = Activity::where('subject_type', Invoice::class)
        ->where('subject_id', $invoice->id)
        ->where('description', 'Rechnung erstellt')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe(auth()->id());
});

it('logs invoice field changes with old and new values', function () {
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Draft]);

    $invoice->update(['status' => InvoiceStatus::Sent]);

    $activity = Activity::where('subject_type', Invoice::class)
        ->where('subject_id', $invoice->id)
        ->latest()
        ->first();

    expect($activity->properties['old']['status'])->toBe('draft');
    expect($activity->properties['attributes']['status'])->toBe('sent');
});

it('generates valid checksums', function () {
    $invoice = Invoice::factory()->create();

    $activity = Activity::where('subject_type', Invoice::class)
        ->where('subject_id', $invoice->id)
        ->first();

    $service = app(AuditChecksumService::class);

    expect($service->verifyChecksum($activity))->toBeTrue();
});

it('prevents modification of audit entries', function () {
    $invoice = Invoice::factory()->create();

    $activity = Activity::where('subject_type', Invoice::class)->first();

    $activity->description = 'Tampered';
    $activity->save();
})->throws(\RuntimeException::class);

it('prevents deletion of audit entries', function () {
    $invoice = Invoice::factory()->create();

    $activity = Activity::where('subject_type', Invoice::class)->first();
    $activity->delete();
})->throws(\RuntimeException::class);
```
