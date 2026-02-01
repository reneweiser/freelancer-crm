# Expense Tracking & EÜR Integration

## Overview

Full expense management system for tracking business expenses, with EÜR (Einnahmenüberschussrechnung) category mapping for annual tax filing. Includes receipt upload, Vorsteuer (input VAT) tracking, and supplier management.

## Data Model

### expenses table

```php
Schema::create('expenses', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();

    // Core expense data
    $table->string('description');
    $table->decimal('amount', 12, 2);  // Gross amount including VAT
    $table->decimal('net_amount', 12, 2); // Net amount before VAT
    $table->decimal('vat_amount', 12, 2)->default(0); // Vorsteuer
    $table->decimal('vat_rate', 5, 2)->default(19.00);

    // Categorization
    $table->string('category'); // EÜR category enum
    $table->string('subcategory')->nullable();

    // Dates
    $table->date('date'); // Expense/invoice date
    $table->date('payment_date')->nullable(); // Actual payment date (Abflussprinzip)

    // Payment
    $table->string('payment_method')->nullable(); // bank_transfer, cash, card, paypal
    $table->string('reference')->nullable(); // Invoice/receipt number from supplier

    // Receipt
    $table->string('receipt_path')->nullable();
    $table->string('receipt_filename')->nullable();

    // Meta
    $table->text('notes')->nullable();
    $table->boolean('is_recurring')->default(false);
    $table->string('recurring_period')->nullable(); // monthly, quarterly, yearly

    $table->timestamps();
    $table->softDeletes();

    // Indexes
    $table->index(['user_id', 'date']);
    $table->index(['user_id', 'category']);
    $table->index(['user_id', 'payment_date']);
});
```

### suppliers table

```php
Schema::create('suppliers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();

    $table->string('name');
    $table->string('contact_name')->nullable();
    $table->string('email')->nullable();
    $table->string('phone')->nullable();

    // Address
    $table->string('street')->nullable();
    $table->string('postal_code')->nullable();
    $table->string('city')->nullable();
    $table->string('country')->default('DE');

    // Tax info
    $table->string('vat_id')->nullable();
    $table->string('tax_number')->nullable();

    // Bank details (for SEPA exports)
    $table->string('iban')->nullable();
    $table->string('bic')->nullable();

    // Default expense settings
    $table->string('default_category')->nullable();
    $table->decimal('default_vat_rate', 5, 2)->default(19.00);

    $table->text('notes')->nullable();

    $table->timestamps();
    $table->softDeletes();

    $table->index(['user_id', 'name']);
});
```

## EÜR Categories Enum

Based on the official "Anlage EÜR" form structure:

```php
namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ExpenseCategory: string implements HasColor, HasLabel
{
    // Betriebsausgaben (Operating Expenses)
    case WarenMaterial = 'waren_material';           // Zeile 26: Waren, Rohstoffe
    case FremdleistungenSubunternehmer = 'fremdleistungen'; // Zeile 27: Fremdleistungen
    case Personal = 'personal';                       // Zeile 28-31: Löhne, Gehälter
    case AbschreibungenAfA = 'abschreibungen';       // Zeile 32-35: AfA
    case RaumkostenMiete = 'raumkosten';             // Zeile 36: Raumkosten, Miete
    case KfzKosten = 'kfz_kosten';                   // Zeile 60-66: Fahrzeugkosten
    case Reisekosten = 'reisekosten';                // Zeile 67-70: Reisekosten
    case BewirtungGeschenke = 'bewirtung';           // Zeile 52-53: Bewirtung, Geschenke
    case Versicherungen = 'versicherungen';          // Zeile 44: Versicherungen
    case BeitraegeGebuehren = 'beitraege';           // Zeile 45: Beiträge, Gebühren
    case Telekommunikation = 'telekommunikation';    // Zeile 47: Telefon, Internet
    case BueroMaterial = 'buero_material';           // Zeile 48: Bürobedarf
    case Fortbildung = 'fortbildung';                // Zeile 49: Fortbildung, Fachliteratur
    case Rechtsberatung = 'rechtsberatung';          // Zeile 50: Rechts-, Steuerberatung
    case Werbung = 'werbung';                        // Zeile 51: Werbung
    case Software = 'software';                       // Zeile 48/GWG: Software, Lizenzen
    case Hardware = 'hardware';                       // AfA/GWG: Computer, Geräte
    case SonstigeBetriebsausgaben = 'sonstige';      // Zeile 71: Sonstige
    case ZinsenFinanzierung = 'zinsen';              // Zeile 56-58: Schuldzinsen
    case BankGebuehren = 'bank_gebuehren';           // Zeile 71: Kontoführung

    public function getLabel(): string
    {
        return match($this) {
            self::WarenMaterial => 'Waren & Material',
            self::FremdleistungenSubunternehmer => 'Fremdleistungen / Subunternehmer',
            self::Personal => 'Personal (Löhne, Gehälter)',
            self::AbschreibungenAfA => 'Abschreibungen (AfA)',
            self::RaumkostenMiete => 'Raumkosten / Miete',
            self::KfzKosten => 'Kfz-Kosten',
            self::Reisekosten => 'Reisekosten',
            self::BewirtungGeschenke => 'Bewirtung & Geschenke',
            self::Versicherungen => 'Versicherungen',
            self::BeitraegeGebuehren => 'Beiträge & Gebühren',
            self::Telekommunikation => 'Telekommunikation',
            self::BueroMaterial => 'Büromaterial & -bedarf',
            self::Fortbildung => 'Fortbildung & Fachliteratur',
            self::Rechtsberatung => 'Rechts- & Steuerberatung',
            self::Werbung => 'Werbung & Marketing',
            self::Software => 'Software & Lizenzen',
            self::Hardware => 'Hardware & Geräte',
            self::SonstigeBetriebsausgaben => 'Sonstige Betriebsausgaben',
            self::ZinsenFinanzierung => 'Zinsen & Finanzierung',
            self::BankGebuehren => 'Bankgebühren',
        };
    }

    public function getColor(): string|array|null
    {
        return match($this) {
            self::WarenMaterial, self::FremdleistungenSubunternehmer => 'primary',
            self::Personal => 'info',
            self::AbschreibungenAfA => 'gray',
            self::RaumkostenMiete => 'warning',
            self::KfzKosten, self::Reisekosten => 'success',
            self::Software, self::Hardware => 'primary',
            default => null,
        };
    }

    /**
     * EÜR form line number mapping
     */
    public function getEurLineNumber(): ?int
    {
        return match($this) {
            self::WarenMaterial => 26,
            self::FremdleistungenSubunternehmer => 27,
            self::Personal => 28,
            self::AbschreibungenAfA => 32,
            self::RaumkostenMiete => 36,
            self::Versicherungen => 44,
            self::BeitraegeGebuehren => 45,
            self::Telekommunikation => 47,
            self::BueroMaterial => 48,
            self::Fortbildung => 49,
            self::Rechtsberatung => 50,
            self::Werbung => 51,
            self::BewirtungGeschenke => 52,
            self::ZinsenFinanzierung => 56,
            self::KfzKosten => 60,
            self::Reisekosten => 67,
            self::SonstigeBetriebsausgaben => 71,
            self::Software, self::Hardware, self::BankGebuehren => 71,
        };
    }

    /**
     * Default VAT deductibility rate (some categories have limits)
     */
    public function getDefaultVatDeductibility(): float
    {
        return match($this) {
            self::BewirtungGeschenke => 0.70, // 70% deductible
            default => 1.00, // 100% deductible
        };
    }

    /**
     * Whether this category typically has VAT
     */
    public function typicallyHasVat(): bool
    {
        return match($this) {
            self::Personal, self::Versicherungen, self::BankGebuehren, self::ZinsenFinanzierung => false,
            default => true,
        };
    }
}
```

## Expense Model

```php
namespace App\Models;

use App\Enums\ExpenseCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Expense extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'user_id',
        'supplier_id',
        'description',
        'amount',
        'net_amount',
        'vat_amount',
        'vat_rate',
        'category',
        'subcategory',
        'date',
        'payment_date',
        'payment_method',
        'reference',
        'receipt_path',
        'receipt_filename',
        'notes',
        'is_recurring',
        'recurring_period',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'category' => ExpenseCategory::class,
            'date' => 'date',
            'payment_date' => 'date',
            'is_recurring' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('expenses');
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    // Scopes

    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeByYear($query, int $year)
    {
        return $query->whereYear('date', $year);
    }

    public function scopeByCategory($query, ExpenseCategory $category)
    {
        return $query->where('category', $category->value);
    }

    public function scopePaidInYear($query, int $year)
    {
        // EÜR uses Abflussprinzip (payment date matters)
        return $query->whereYear('payment_date', $year);
    }

    // Calculations

    public function calculateAmounts(): void
    {
        if ($this->vat_rate > 0) {
            $this->net_amount = round($this->amount / (1 + $this->vat_rate / 100), 2);
            $this->vat_amount = round($this->amount - $this->net_amount, 2);
        } else {
            $this->net_amount = $this->amount;
            $this->vat_amount = 0;
        }
    }

    /**
     * Get deductible VAT amount based on category rules
     */
    public function getDeductibleVatAttribute(): float
    {
        $deductibility = $this->category?->getDefaultVatDeductibility() ?? 1.0;
        return round($this->vat_amount * $deductibility, 2);
    }

    // Receipt handling

    public function getReceiptUrlAttribute(): ?string
    {
        if (!$this->receipt_path) {
            return null;
        }

        return Storage::url($this->receipt_path);
    }
}
```

## Supplier Model

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'contact_name',
        'email',
        'phone',
        'street',
        'postal_code',
        'city',
        'country',
        'vat_id',
        'tax_number',
        'iban',
        'bic',
        'default_category',
        'default_vat_rate',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'default_category' => ExpenseCategory::class,
            'default_vat_rate' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->name;
    }

    public function getFullAddressAttribute(): string
    {
        return collect([
            $this->street,
            trim("{$this->postal_code} {$this->city}"),
            $this->country !== 'DE' ? $this->country : null,
        ])->filter()->join("\n");
    }
}
```

## Filament Resources

### ExpenseResource

```php
namespace App\Filament\Resources\Expenses;

use App\Models\Expense;
use Filament\Resources\Resource;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;
    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';
    protected static ?string $navigationLabel = 'Ausgaben';
    protected static ?string $navigationGroup = 'Finanzen';
    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Ausgabe';
    protected static ?string $pluralModelLabel = 'Ausgaben';

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'edit' => Pages\EditExpense::route('/{record}/edit'),
        ];
    }
}
```

### ExpenseForm Schema

```php
namespace App\Filament\Resources\Expenses\Schemas;

use App\Enums\ExpenseCategory;
use Filament\Forms;

class ExpenseForm
{
    public static function schema(): array
    {
        return [
            Forms\Components\Section::make('Ausgabe')
                ->schema([
                    Forms\Components\Select::make('supplier_id')
                        ->label('Lieferant')
                        ->relationship('supplier', 'name')
                        ->searchable()
                        ->preload()
                        ->createOptionForm(SupplierForm::simpleSchema())
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if ($state) {
                                $supplier = Supplier::find($state);
                                if ($supplier->default_category) {
                                    $set('category', $supplier->default_category->value);
                                }
                                if ($supplier->default_vat_rate) {
                                    $set('vat_rate', $supplier->default_vat_rate);
                                }
                            }
                        }),

                    Forms\Components\TextInput::make('description')
                        ->label('Beschreibung')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\Select::make('category')
                        ->label('Kategorie')
                        ->options(ExpenseCategory::class)
                        ->required()
                        ->searchable()
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set) {
                            $category = ExpenseCategory::tryFrom($state);
                            if ($category && !$category->typicallyHasVat()) {
                                $set('vat_rate', 0);
                            }
                        }),

                    Forms\Components\TextInput::make('reference')
                        ->label('Rechnungs-/Belegnummer')
                        ->maxLength(100),
                ])
                ->columns(2),

            Forms\Components\Section::make('Beträge')
                ->schema([
                    Forms\Components\TextInput::make('amount')
                        ->label('Bruttobetrag')
                        ->required()
                        ->numeric()
                        ->prefix('€')
                        ->reactive()
                        ->afterStateUpdated(fn ($state, callable $set, $get) =>
                            self::calculateAmounts($state, $get('vat_rate'), $set)
                        ),

                    Forms\Components\Select::make('vat_rate')
                        ->label('USt-Satz')
                        ->options([
                            '19.00' => '19% (Standard)',
                            '7.00' => '7% (ermäßigt)',
                            '0.00' => '0% (keine USt)',
                        ])
                        ->default('19.00')
                        ->reactive()
                        ->afterStateUpdated(fn ($state, callable $set, $get) =>
                            self::calculateAmounts($get('amount'), $state, $set)
                        ),

                    Forms\Components\TextInput::make('net_amount')
                        ->label('Nettobetrag')
                        ->numeric()
                        ->prefix('€')
                        ->disabled()
                        ->dehydrated(),

                    Forms\Components\TextInput::make('vat_amount')
                        ->label('Vorsteuer')
                        ->numeric()
                        ->prefix('€')
                        ->disabled()
                        ->dehydrated(),
                ])
                ->columns(4),

            Forms\Components\Section::make('Datum & Zahlung')
                ->schema([
                    Forms\Components\DatePicker::make('date')
                        ->label('Belegdatum')
                        ->required()
                        ->default(now()),

                    Forms\Components\DatePicker::make('payment_date')
                        ->label('Zahlungsdatum')
                        ->helperText('Für EÜR relevant (Abflussprinzip)'),

                    Forms\Components\Select::make('payment_method')
                        ->label('Zahlungsart')
                        ->options([
                            'bank_transfer' => 'Überweisung',
                            'cash' => 'Bargeld',
                            'card' => 'Karte',
                            'paypal' => 'PayPal',
                            'direct_debit' => 'Lastschrift',
                        ]),
                ])
                ->columns(3),

            Forms\Components\Section::make('Beleg')
                ->schema([
                    Forms\Components\FileUpload::make('receipt_path')
                        ->label('Beleg hochladen')
                        ->disk('local')
                        ->directory('receipts')
                        ->visibility('private')
                        ->acceptedFileTypes(['image/*', 'application/pdf'])
                        ->maxSize(10240) // 10MB
                        ->imagePreviewHeight('250')
                        ->downloadable()
                        ->openable(),

                    Forms\Components\Textarea::make('notes')
                        ->label('Notizen')
                        ->rows(2),
                ]),

            Forms\Components\Section::make('Wiederkehrend')
                ->schema([
                    Forms\Components\Toggle::make('is_recurring')
                        ->label('Wiederkehrende Ausgabe')
                        ->reactive(),

                    Forms\Components\Select::make('recurring_period')
                        ->label('Intervall')
                        ->options([
                            'monthly' => 'Monatlich',
                            'quarterly' => 'Vierteljährlich',
                            'yearly' => 'Jährlich',
                        ])
                        ->visible(fn ($get) => $get('is_recurring')),
                ])
                ->columns(2)
                ->collapsed(),
        ];
    }

    protected static function calculateAmounts(?float $amount, ?string $vatRate, callable $set): void
    {
        if (!$amount) return;

        $rate = floatval($vatRate ?? 19);
        $netAmount = round($amount / (1 + $rate / 100), 2);
        $vatAmount = round($amount - $netAmount, 2);

        $set('net_amount', $netAmount);
        $set('vat_amount', $vatAmount);
    }
}
```

### ExpenseTable Schema

```php
namespace App\Filament\Resources\Expenses\Schemas;

use Filament\Tables;

class ExpensesTable
{
    public static function schema(): array
    {
        return [
            Tables\Columns\TextColumn::make('date')
                ->label('Datum')
                ->date('d.m.Y')
                ->sortable(),

            Tables\Columns\TextColumn::make('supplier.name')
                ->label('Lieferant')
                ->searchable()
                ->toggleable(),

            Tables\Columns\TextColumn::make('description')
                ->label('Beschreibung')
                ->searchable()
                ->limit(40),

            Tables\Columns\TextColumn::make('category')
                ->label('Kategorie')
                ->badge()
                ->searchable(),

            Tables\Columns\TextColumn::make('amount')
                ->label('Brutto')
                ->money('EUR')
                ->alignEnd()
                ->sortable(),

            Tables\Columns\TextColumn::make('vat_amount')
                ->label('Vorsteuer')
                ->money('EUR')
                ->alignEnd()
                ->toggleable(),

            Tables\Columns\TextColumn::make('payment_date')
                ->label('Bezahlt am')
                ->date('d.m.Y')
                ->toggleable(),

            Tables\Columns\IconColumn::make('receipt_path')
                ->label('Beleg')
                ->boolean()
                ->trueIcon('heroicon-o-document')
                ->falseIcon('heroicon-o-x-mark'),
        ];
    }

    public static function filters(): array
    {
        return [
            Tables\Filters\SelectFilter::make('category')
                ->label('Kategorie')
                ->options(ExpenseCategory::class),

            Tables\Filters\SelectFilter::make('supplier')
                ->relationship('supplier', 'name')
                ->searchable()
                ->preload(),

            Tables\Filters\Filter::make('date_range')
                ->form([
                    Forms\Components\DatePicker::make('from')->label('Von'),
                    Forms\Components\DatePicker::make('until')->label('Bis'),
                ])
                ->query(function ($query, array $data) {
                    return $query
                        ->when($data['from'], fn ($q) => $q->whereDate('date', '>=', $data['from']))
                        ->when($data['until'], fn ($q) => $q->whereDate('date', '<=', $data['until']));
                }),

            Tables\Filters\TernaryFilter::make('has_receipt')
                ->label('Beleg vorhanden')
                ->queries(
                    true: fn ($query) => $query->whereNotNull('receipt_path'),
                    false: fn ($query) => $query->whereNull('receipt_path'),
                ),
        ];
    }
}
```

## EÜR Export Service

```php
namespace App\Services;

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use App\Models\Invoice;
use Carbon\Carbon;

class EurExportService
{
    /**
     * Generate EÜR summary for a tax year
     */
    public function generateSummary(User $user, int $year): array
    {
        // Income (Einnahmen) - based on payment date
        $income = Invoice::where('user_id', $user->id)
            ->where('status', InvoiceStatus::Paid)
            ->whereYear('paid_at', $year)
            ->get();

        $totalIncome = $income->sum('total');
        $totalIncomeVat = $income->sum('vat_amount');

        // Expenses (Ausgaben) - based on payment date (Abflussprinzip)
        $expenses = Expense::where('user_id', $user->id)
            ->whereYear('payment_date', $year)
            ->get();

        // Group expenses by EÜR category
        $expensesByCategory = $expenses->groupBy('category')
            ->map(fn ($group) => [
                'category' => ExpenseCategory::from($group->first()->category),
                'eur_line' => ExpenseCategory::from($group->first()->category)->getEurLineNumber(),
                'total' => $group->sum('amount'),
                'net' => $group->sum('net_amount'),
                'vat' => $group->sum('vat_amount'),
                'deductible_vat' => $group->sum('deductible_vat'),
                'count' => $group->count(),
            ])
            ->sortBy('eur_line')
            ->values();

        $totalExpenses = $expenses->sum('amount');
        $totalExpensesNet = $expenses->sum('net_amount');
        $totalVorsteuer = $expenses->sum('deductible_vat');

        // Profit calculation
        $profit = $totalIncome - $totalExpensesNet;

        // VAT summary
        $vatPayable = $totalIncomeVat - $totalVorsteuer;

        return [
            'year' => $year,
            'income' => [
                'total' => $totalIncome,
                'net' => $totalIncome - $totalIncomeVat,
                'vat' => $totalIncomeVat,
                'count' => $income->count(),
            ],
            'expenses' => [
                'total' => $totalExpenses,
                'net' => $totalExpensesNet,
                'vat' => $totalVorsteuer,
                'count' => $expenses->count(),
                'by_category' => $expensesByCategory,
            ],
            'profit' => $profit,
            'vat_summary' => [
                'collected' => $totalIncomeVat,
                'deductible' => $totalVorsteuer,
                'payable' => $vatPayable,
            ],
        ];
    }

    /**
     * Export as CSV for tax advisor
     */
    public function exportExpensesCsv(User $user, int $year): string
    {
        $expenses = Expense::where('user_id', $user->id)
            ->whereYear('payment_date', $year)
            ->with('supplier')
            ->orderBy('payment_date')
            ->get();

        $csv = Writer::createFromString();
        $csv->insertOne([
            'Belegdatum',
            'Zahlungsdatum',
            'Lieferant',
            'Beschreibung',
            'Kategorie',
            'EÜR-Zeile',
            'Brutto',
            'Netto',
            'Vorsteuer',
            'USt-Satz',
            'Belegnummer',
        ]);

        foreach ($expenses as $expense) {
            $csv->insertOne([
                $expense->date->format('d.m.Y'),
                $expense->payment_date?->format('d.m.Y'),
                $expense->supplier?->name ?? '',
                $expense->description,
                $expense->category->getLabel(),
                $expense->category->getEurLineNumber(),
                number_format($expense->amount, 2, ',', '.'),
                number_format($expense->net_amount, 2, ',', '.'),
                number_format($expense->vat_amount, 2, ',', '.'),
                $expense->vat_rate . '%',
                $expense->reference ?? '',
            ]);
        }

        return $csv->toString();
    }
}
```

## Test Cases

```php
// tests/Feature/ExpenseTest.php

it('calculates VAT amounts correctly', function () {
    $expense = Expense::factory()->create([
        'amount' => 119.00,
        'vat_rate' => 19.00,
    ]);
    $expense->calculateAmounts();

    expect($expense->net_amount)->toBe(100.00);
    expect($expense->vat_amount)->toBe(19.00);
});

it('calculates reduced VAT correctly', function () {
    $expense = Expense::factory()->create([
        'amount' => 107.00,
        'vat_rate' => 7.00,
    ]);
    $expense->calculateAmounts();

    expect($expense->net_amount)->toBe(100.00);
    expect($expense->vat_amount)->toBe(7.00);
});

it('applies VAT deductibility for entertainment', function () {
    $expense = Expense::factory()->create([
        'category' => ExpenseCategory::BewirtungGeschenke,
        'amount' => 119.00,
        'vat_rate' => 19.00,
    ]);
    $expense->calculateAmounts();

    // 70% deductible for entertainment
    expect($expense->deductible_vat)->toBe(13.30);
});

it('groups expenses by EÜR category', function () {
    Expense::factory()->count(3)->create([
        'category' => ExpenseCategory::Software,
        'payment_date' => now(),
    ]);

    $service = app(EurExportService::class);
    $summary = $service->generateSummary($this->user, now()->year);

    expect($summary['expenses']['by_category'])
        ->toHaveCount(1)
        ->first()->count->toBe(3);
});

it('uses payment date for EÜR calculation', function () {
    // Expense dated 2025, paid 2026
    $expense = Expense::factory()->create([
        'date' => '2025-12-15',
        'payment_date' => '2026-01-05',
        'amount' => 100,
    ]);

    $service = app(EurExportService::class);

    $summary2025 = $service->generateSummary($this->user, 2025);
    $summary2026 = $service->generateSummary($this->user, 2026);

    expect($summary2025['expenses']['count'])->toBe(0);
    expect($summary2026['expenses']['count'])->toBe(1);
});
```
