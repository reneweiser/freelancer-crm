# Filament 4.6 API Reference

Quick reference for Filament 4.6 patterns relevant to this CRM project.

> Based on Filament 4.6 (PHP 8.4, Laravel 12)

---

## Important: Use Laravel Boost MCP for Documentation

**Do not rely on learned behavior or web searches for Laravel 12 or Filament 4 APIs.**

Instead, use **Laravel Boost** - an MCP that provides direct access to Laravel and Filament documentation.

### Setup Laravel Boost

```bash
sail composer require laravel/boost --dev
sail artisan boost:install
# Select "Laravel" and "Filament" when prompted
```

### What Boost Enables

- Search Laravel 12 documentation for Eloquent, routing, validation, middleware, queues
- Search Filament 4 documentation directly
- Get accurate, up-to-date API patterns
- Avoid hallucinated or outdated code
- Write idiomatic Laravel and Filament code

### When Implementing Features

1. Install Boost first (one-time setup)
2. Query the documentation for unfamiliar Laravel or Filament components
3. Follow the patterns returned by Boost

---

## Installation & Setup

```bash
# Install Filament 4.6
sail composer require filament/filament:"^4.6" -W

# Create admin panel
sail artisan filament:install --panels

# Create user
sail artisan make:filament-user
```

---

## Resource Generation

```bash
# Standard resource with pages
sail artisan make:filament-resource Client

# Simple resource (modal-based CRUD)
sail artisan make:filament-resource Client --simple

# Generate from database columns
sail artisan make:filament-resource Client --generate

# With soft deletes support
sail artisan make:filament-resource Client --soft-deletes
```

### New Directory Structure (Filament 4)

```bash
# Migrate to new structure
sail artisan filament:upgrade-directory-structure-to-v4
```

New structure:
```
Clients/
├── ClientResource.php
├── Pages/
│   ├── CreateClient.php
│   ├── EditClient.php
│   └── ListClients.php
├── Schemas/
│   └── ClientForm.php
└── Tables/
    └── ClientsTable.php
```

---

## Form Components

### TextInput

```php
Forms\Components\TextInput::make('email')
    ->label('E-Mail')
    ->email()
    ->required()
    ->unique(ignoreRecord: true)
    ->maxLength(255)
    ->placeholder('name@example.com')
    ->copyable()  // New in v4
    ->helperText('Die E-Mail-Adresse des Kunden');
```

### Select

```php
Forms\Components\Select::make('client_id')
    ->label('Kunde')
    ->relationship('client', 'company_name')
    ->searchable()
    ->preload()
    ->required()
    ->createOptionForm([...])  // Inline creation
    ->editOptionForm([...]);   // Inline editing
```

### Money Input

```php
Forms\Components\TextInput::make('hourly_rate')
    ->label('Stundensatz')
    ->numeric()
    ->prefix('€')
    ->step(0.01)
    ->minValue(0);
```

### Reactive Fields

```php
Forms\Components\Select::make('type')
    ->options([...])
    ->live()  // Triggers re-render on change
    ->afterStateUpdated(fn (Set $set) => $set('hourly_rate', null));

Forms\Components\TextInput::make('hourly_rate')
    ->visible(fn (Get $get) => $get('type') === 'hourly');
```

### Repeater

```php
Forms\Components\Repeater::make('items')
    ->relationship()
    ->schema([...])
    ->columns(5)
    ->reorderable()
    ->collapsible()
    ->cloneable()
    ->defaultItems(1)
    ->addActionLabel('Position hinzufügen')
    ->live()
    ->afterStateUpdated(fn (Set $set, Get $get) => $this->calculate($set, $get));
```

### Section

```php
Forms\Components\Section::make('Kontaktdaten')
    ->description('Persönliche Daten des Ansprechpartners')
    ->icon('heroicon-o-user')
    ->columns(2)
    ->collapsible()
    ->collapsed()
    ->schema([...]);
```

---

## Table Components

### Columns

```php
Tables\Columns\TextColumn::make('number')
    ->label('Nr.')
    ->searchable()
    ->sortable()
    ->copyable();

Tables\Columns\TextColumn::make('total')
    ->label('Betrag')
    ->money('EUR')
    ->sortable();

Tables\Columns\TextColumn::make('status')
    ->badge();  // Uses enum colors

Tables\Columns\TextColumn::make('created_at')
    ->dateTime('d.m.Y')
    ->sortable()
    ->toggleable(isToggledHiddenByDefault: true);
```

### Aggregates

```php
Tables\Columns\TextColumn::make('projects_count')
    ->counts('projects');

Tables\Columns\TextColumn::make('invoices_sum_total')
    ->sum('invoices', 'total')
    ->money('EUR');
```

### Filters

```php
Tables\Filters\SelectFilter::make('status')
    ->options(InvoiceStatus::class);

Tables\Filters\Filter::make('date_range')
    ->form([
        Forms\Components\DatePicker::make('from'),
        Forms\Components\DatePicker::make('until'),
    ])
    ->query(function (Builder $query, array $data): Builder {
        return $query
            ->when($data['from'], fn ($q, $d) => $q->whereDate('issued_at', '>=', $d))
            ->when($data['until'], fn ($q, $d) => $q->whereDate('issued_at', '<=', $d));
    });
```

### Filter Layout (Filament 4 Change)

```php
// Filters are deferred by default in v4
// To restore v3 behavior:
->deferFilters(false)

// Modal layout
->filtersLayout(FiltersLayout::Modal)
```

---

## Actions

### Table Actions

```php
Tables\Actions\ActionGroup::make([
    Tables\Actions\ViewAction::make(),
    Tables\Actions\EditAction::make(),
    Tables\Actions\Action::make('download')
        ->label('PDF')
        ->icon('heroicon-o-arrow-down-tray')
        ->action(fn (Invoice $record) => $record->downloadPdf()),
    Tables\Actions\Action::make('send')
        ->label('Versenden')
        ->icon('heroicon-o-paper-airplane')
        ->requiresConfirmation()
        ->action(fn (Invoice $record) => $record->send())
        ->visible(fn (Invoice $record) => $record->status === InvoiceStatus::Draft),
]);
```

### Actions with Forms

```php
Tables\Actions\Action::make('markPaid')
    ->label('Als bezahlt markieren')
    ->icon('heroicon-o-check-circle')
    ->form([
        Forms\Components\DatePicker::make('paid_at')
            ->label('Zahlungsdatum')
            ->default(now())
            ->required(),
        Forms\Components\TextInput::make('payment_method')
            ->label('Zahlungsart'),
    ])
    ->action(function (Invoice $record, array $data) {
        $record->update([
            'status' => InvoiceStatus::Paid,
            'paid_at' => $data['paid_at'],
            'payment_method' => $data['payment_method'],
        ]);
    });
```

### Bulk Actions

```php
Tables\Actions\BulkActionGroup::make([
    Tables\Actions\DeleteBulkAction::make(),
    ExportBulkAction::make()
        ->exporter(InvoiceExporter::class)
        ->formats([ExportFormat::Csv, ExportFormat::Xlsx]),
]);
```

### Rate Limiting (New in v4)

```php
Tables\Actions\Action::make('send')
    ->rateLimit(3); // 3 per minute per user
```

---

## Relation Managers

```bash
sail artisan make:filament-relation-manager ClientResource projects title
```

```php
// In Resource
public static function getRelations(): array
{
    return [
        ProjectsRelationManager::class,
        InvoicesRelationManager::class,
    ];
}
```

---

## Widgets

### Stats Overview

```php
class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Offene Rechnungen', Invoice::unpaid()->count())
                ->description(Number::currency(Invoice::unpaid()->sum('total'), 'EUR', 'de_DE'))
                ->descriptionIcon('heroicon-o-currency-euro')
                ->color('warning')
                ->chart([7, 3, 4, 5, 6, 3, 5]),  // Mini sparkline
        ];
    }
}
```

### Table Widget

```php
class UpcomingRemindersWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(Reminder::upcoming()->limit(10))
            ->columns([...])
            ->actions([...]);
    }
}
```

### Chart Widget

```php
class RevenueChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Monatlicher Umsatz';

    protected function getData(): array
    {
        return [
            'datasets' => [
                [
                    'label' => 'Umsatz',
                    'data' => [1000, 2000, 1500, 3000, 2500, 4000],
                ],
            ],
            'labels' => ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
```

---

## Export/Import

### Exporter

```bash
sail artisan make:filament-exporter Invoice
```

```php
class InvoiceExporter extends Exporter
{
    protected static ?string $model = Invoice::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('number')->label('Rechnungsnummer'),
            ExportColumn::make('client.company_name')->label('Kunde'),
            ExportColumn::make('issued_at')->label('Datum'),
            ExportColumn::make('total')->label('Betrag'),
            ExportColumn::make('status')->label('Status'),
        ];
    }
}
```

### Export Action

```php
ExportAction::make()
    ->exporter(InvoiceExporter::class)
    ->formats([
        ExportFormat::Csv,
        ExportFormat::Xlsx,
    ])
    ->fileName(fn () => 'rechnungen-' . now()->format('Y-m-d'));
```

---

## Enums with Filament

```php
enum InvoiceStatus: string implements HasLabel, HasColor
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Paid = 'paid';

    public function getLabel(): string
    {
        return match($this) {
            self::Draft => 'Entwurf',
            self::Sent => 'Gesendet',
            self::Paid => 'Bezahlt',
        };
    }

    public function getColor(): string|array|null
    {
        return match($this) {
            self::Draft => 'gray',
            self::Sent => 'info',
            self::Paid => 'success',
        };
    }
}
```

---

## Panel Configuration

```php
// AdminPanelProvider.php
return $panel
    ->default()
    ->id('admin')
    ->path('crm')
    ->login()
    ->profile()
    ->colors([
        'primary' => Color::Blue,
    ])
    ->font('Inter')
    ->brandName('Freelancer CRM')
    ->favicon(asset('favicon.ico'))
    ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
    ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
    ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
    ->middleware([...])
    ->authMiddleware([Authenticate::class])
    ->databaseNotifications()
    ->sidebarCollapsibleOnDesktop()
    ->maxContentWidth(MaxWidth::Full);
```

---

## Breaking Changes from v3

| Change | v3 | v4 |
|--------|----|----|
| Grid/Section span | Full width by default | Use `->columnSpanFull()` |
| Filters | Apply immediately | Deferred by default |
| File visibility | Public | Private on non-local disks |
| Tailwind | v3 | v4 required for custom themes |
| `unique()` | Doesn't ignore record | Ignores record by default |
| Enums | Return string value | Return enum instance |

---

## Useful Packages

| Package | Purpose |
|---------|---------|
| `laravel/boost` | **Required** - MCP for Laravel & Filament docs access |
| `barryvdh/laravel-dompdf` | PDF generation |
| `pxlrbt/filament-excel` | Enhanced Excel exports |
| `flowframe/laravel-trend` | Chart data from Eloquent |
| `filament/spatie-laravel-media-library-plugin` | File uploads |
| `filament/spatie-laravel-settings-plugin` | Settings management |

### Installation Commands (via Sail)

```bash
# Required: Laravel Boost for AI assistance
sail composer require laravel/boost --dev
sail artisan boost:install

# PDF generation
sail composer require barryvdh/laravel-dompdf

# Enhanced Excel exports
sail composer require pxlrbt/filament-excel

# Chart data helper
sail composer require flowframe/laravel-trend

# Media library
sail composer require filament/spatie-laravel-media-library-plugin:"^4.0" -W

# Settings management
sail composer require filament/spatie-laravel-settings-plugin:"^4.0" -W
```
