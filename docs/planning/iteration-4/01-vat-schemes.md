# VAT Schemes & Invoice Compliance

## Overview

German tax law requires different VAT treatments depending on business type, client location, and transaction type. This document specifies the VAT scheme system and invoice compliance requirements.

## VAT Scheme Enum

```php
namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum VatScheme: string implements HasColor, HasLabel
{
    case Standard = 'standard';           // 19% German VAT
    case Reduced = 'reduced';             // 7% reduced rate
    case Kleinunternehmer = 'kleinunternehmer';  // § 19 UStG exemption
    case ReverseCharge = 'reverse_charge'; // § 13b UStG - EU B2B
    case ExportNonEU = 'export_non_eu';   // Export to non-EU (0%)
    case ExemptEducation = 'exempt_education'; // § 4 Nr. 21 - educational services

    public function getLabel(): string
    {
        return match($this) {
            self::Standard => 'Standard (19%)',
            self::Reduced => 'Ermäßigt (7%)',
            self::Kleinunternehmer => 'Kleinunternehmer (§ 19 UStG)',
            self::ReverseCharge => 'Reverse Charge (§ 13b UStG)',
            self::ExportNonEU => 'Export Drittland (0%)',
            self::ExemptEducation => 'Steuerbefreit Bildung (§ 4 Nr. 21)',
        };
    }

    public function getColor(): string|array|null
    {
        return match($this) {
            self::Standard => 'primary',
            self::Reduced => 'info',
            self::Kleinunternehmer => 'warning',
            self::ReverseCharge => 'success',
            self::ExportNonEU => 'gray',
            self::ExemptEducation => 'gray',
        };
    }

    public function getVatRate(): float
    {
        return match($this) {
            self::Standard => 19.00,
            self::Reduced => 7.00,
            self::Kleinunternehmer => 0.00,
            self::ReverseCharge => 0.00,
            self::ExportNonEU => 0.00,
            self::ExemptEducation => 0.00,
        };
    }

    public function getInvoiceNotice(): ?string
    {
        return match($this) {
            self::Standard => null,
            self::Reduced => null,
            self::Kleinunternehmer => 'Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.',
            self::ReverseCharge => 'Steuerschuldnerschaft des Leistungsempfängers (Reverse Charge gemäß § 13b UStG).',
            self::ExportNonEU => 'Steuerfreie Ausfuhrlieferung.',
            self::ExemptEducation => 'Umsatzsteuerbefreit nach § 4 Nr. 21 UStG.',
        };
    }

    public function requiresClientVatId(): bool
    {
        return $this === self::ReverseCharge;
    }
}
```

## Auto-Detection Logic

The system will suggest a VAT scheme based on client data:

```php
class VatSchemeDetector
{
    private const EU_COUNTRIES = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
        'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL',
        'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
        // DE excluded - domestic
    ];

    public function suggestScheme(Client $client, User $user): VatScheme
    {
        // Check if user is Kleinunternehmer (from settings)
        $isKleinunternehmer = $user->settings()->get('is_kleinunternehmer', false);

        if ($isKleinunternehmer) {
            return VatScheme::Kleinunternehmer;
        }

        $country = $client->country ?? 'DE';
        $hasVatId = !empty($client->vat_id);

        // Domestic (Germany)
        if ($country === 'DE') {
            return VatScheme::Standard;
        }

        // EU country with valid VAT ID → Reverse Charge
        if (in_array($country, self::EU_COUNTRIES) && $hasVatId) {
            return VatScheme::ReverseCharge;
        }

        // EU country without VAT ID → Standard German VAT
        if (in_array($country, self::EU_COUNTRIES) && !$hasVatId) {
            return VatScheme::Standard;
        }

        // Non-EU country → Export
        return VatScheme::ExportNonEU;
    }
}
```

## Database Changes

### invoices table migration

```php
Schema::table('invoices', function (Blueprint $table) {
    // VAT scheme for the entire invoice
    $table->string('vat_scheme')->default('standard')->after('vat_rate');

    // Service delivery date (Leistungsdatum) - distinct from service period
    $table->date('service_date')->nullable()->after('service_period_end');

    // Legal notice text (auto-generated from vat_scheme, but can be customized)
    $table->text('legal_notice')->nullable()->after('footer_text');
});
```

### clients table migration

```php
Schema::table('clients', function (Blueprint $table) {
    // Ensure VAT ID field exists (already present, but verify format)
    // vat_id stores the full EU VAT ID including country prefix (e.g., "DE123456789")

    // Add tax number field for German clients (Steuernummer)
    $table->string('tax_number')->nullable()->after('vat_id');
});
```

### users/settings additions

Add to Settings page and SettingsService:

```php
// New settings keys
'is_kleinunternehmer' => false,      // Boolean: User is small business exempt
'default_vat_scheme' => 'standard',  // Default scheme for new invoices
'kleinunternehmer_threshold' => 22000, // Revenue threshold for status
```

## Invoice Model Changes

```php
// app/Models/Invoice.php

protected function casts(): array
{
    return [
        // ... existing casts
        'vat_scheme' => VatScheme::class,
        'service_date' => 'date',
    ];
}

/**
 * Get the appropriate legal notice based on VAT scheme
 */
public function getLegalNoticeTextAttribute(): ?string
{
    // Custom notice takes precedence
    if ($this->legal_notice) {
        return $this->legal_notice;
    }

    return $this->vat_scheme?->getInvoiceNotice();
}

/**
 * Calculate VAT based on scheme
 */
public function calculateTotals(): void
{
    $subtotal = $this->items->sum(fn ($item) => $item->quantity * $item->unit_price);

    $vatRate = $this->vat_scheme?->getVatRate() ?? $this->vat_rate;
    $vatAmount = $subtotal * ($vatRate / 100);

    $this->update([
        'subtotal' => $subtotal,
        'vat_rate' => $vatRate,
        'vat_amount' => $vatAmount,
        'total' => $subtotal + $vatAmount,
    ]);
}
```

## Filament Form Changes

### InvoiceForm.php additions

```php
// In the invoice form schema

Forms\Components\Select::make('vat_scheme')
    ->label('Umsatzsteuer-Regelung')
    ->options(VatScheme::class)
    ->default(fn () => auth()->user()->settings()->get('default_vat_scheme', 'standard'))
    ->reactive()
    ->afterStateUpdated(function ($state, callable $set) {
        $scheme = VatScheme::tryFrom($state);
        if ($scheme) {
            $set('vat_rate', $scheme->getVatRate());
        }
    })
    ->helperText(fn ($state) => VatScheme::tryFrom($state)?->getInvoiceNotice())
    ->required(),

Forms\Components\DatePicker::make('service_date')
    ->label('Leistungsdatum')
    ->helperText('Datum der Leistungserbringung (falls abweichend vom Rechnungsdatum)')
    ->nullable(),

Forms\Components\Textarea::make('legal_notice')
    ->label('Rechtlicher Hinweis')
    ->helperText('Wird automatisch basierend auf der USt-Regelung generiert, kann aber angepasst werden')
    ->placeholder(fn ($get) => VatScheme::tryFrom($get('vat_scheme'))?->getInvoiceNotice())
    ->rows(2),
```

### Client selection with VAT suggestion

```php
Forms\Components\Select::make('client_id')
    ->relationship('client', 'company_name')
    ->searchable()
    ->preload()
    ->reactive()
    ->afterStateUpdated(function ($state, callable $set) {
        if ($state) {
            $client = Client::find($state);
            $detector = new VatSchemeDetector();
            $suggestedScheme = $detector->suggestScheme($client, auth()->user());
            $set('vat_scheme', $suggestedScheme->value);
        }
    }),
```

## PDF Template Changes

Update `resources/views/pdf/invoice.blade.php`:

```blade
{{-- Add service date if set --}}
@if($invoice->service_date)
<tr>
    <td class="label">Leistungsdatum:</td>
    <td>{{ $invoice->service_date->format('d.m.Y') }}</td>
</tr>
@elseif($invoice->service_period_start && $invoice->service_period_end)
<tr>
    <td class="label">Leistungszeitraum:</td>
    <td>{{ $invoice->service_period_start->format('d.m.Y') }} - {{ $invoice->service_period_end->format('d.m.Y') }}</td>
</tr>
@endif

{{-- Legal notice section (before payment details) --}}
@if($invoice->legal_notice_text)
<div class="legal-notice">
    <p>{{ $invoice->legal_notice_text }}</p>
</div>
@endif

{{-- For reverse charge, show both VAT IDs --}}
@if($invoice->vat_scheme === \App\Enums\VatScheme::ReverseCharge)
<div class="vat-ids">
    <p><strong>USt-IdNr. Leistungserbringer:</strong> {{ $business['vat_id'] }}</p>
    <p><strong>USt-IdNr. Leistungsempfänger:</strong> {{ $invoice->client->vat_id }}</p>
</div>
@endif
```

## Validation Rules

### Invoice Compliance Validation

```php
class InvoiceComplianceValidator
{
    public function validate(Invoice $invoice): ValidationResult
    {
        $errors = [];

        // § 14 UStG Pflichtangaben
        if (empty($invoice->number)) {
            $errors[] = 'Rechnungsnummer fehlt';
        }

        if (empty($invoice->issued_at)) {
            $errors[] = 'Rechnungsdatum fehlt';
        }

        // Service date or period required
        if (empty($invoice->service_date) &&
            (empty($invoice->service_period_start) || empty($invoice->service_period_end))) {
            $errors[] = 'Leistungsdatum oder Leistungszeitraum fehlt';
        }

        // Client address required
        if (empty($invoice->client->street) || empty($invoice->client->city)) {
            $errors[] = 'Vollständige Kundenadresse fehlt';
        }

        // Reverse charge requires client VAT ID
        if ($invoice->vat_scheme === VatScheme::ReverseCharge &&
            empty($invoice->client->vat_id)) {
            $errors[] = 'Reverse Charge erfordert USt-IdNr. des Kunden';
        }

        // Business VAT ID required for reverse charge
        $businessVatId = $invoice->user->settings()->get('vat_id');
        if ($invoice->vat_scheme === VatScheme::ReverseCharge && empty($businessVatId)) {
            $errors[] = 'Reverse Charge erfordert eigene USt-IdNr.';
        }

        return new ValidationResult($errors);
    }
}
```

## Settings Page Additions

Add to `app/Filament/Pages/Settings.php`:

```php
Forms\Components\Section::make('Umsatzsteuer')
    ->description('Einstellungen zur Umsatzsteuer-Behandlung')
    ->schema([
        Forms\Components\Toggle::make('is_kleinunternehmer')
            ->label('Kleinunternehmerregelung')
            ->helperText('Aktivieren wenn Sie die Kleinunternehmerregelung nach § 19 UStG nutzen')
            ->reactive(),

        Forms\Components\Select::make('default_vat_scheme')
            ->label('Standard USt-Regelung')
            ->options(VatScheme::class)
            ->default('standard')
            ->visible(fn ($get) => !$get('is_kleinunternehmer')),

        Forms\Components\TextInput::make('vat_id')
            ->label('USt-IdNr.')
            ->placeholder('DE123456789')
            ->helperText('Umsatzsteuer-Identifikationsnummer für EU-Geschäfte')
            ->visible(fn ($get) => !$get('is_kleinunternehmer')),
    ]),
```

## Test Cases

```php
// tests/Feature/VatSchemeTest.php

it('detects reverse charge for EU client with VAT ID', function () {
    $client = Client::factory()->create([
        'country' => 'FR',
        'vat_id' => 'FR12345678901',
    ]);

    $detector = new VatSchemeDetector();
    $scheme = $detector->suggestScheme($client, $this->user);

    expect($scheme)->toBe(VatScheme::ReverseCharge);
});

it('uses standard VAT for domestic clients', function () {
    $client = Client::factory()->create(['country' => 'DE']);

    $detector = new VatSchemeDetector();
    $scheme = $detector->suggestScheme($client, $this->user);

    expect($scheme)->toBe(VatScheme::Standard);
});

it('applies Kleinunternehmer for eligible users', function () {
    $this->user->settings()->set('is_kleinunternehmer', true);
    $client = Client::factory()->create(['country' => 'DE']);

    $detector = new VatSchemeDetector();
    $scheme = $detector->suggestScheme($client, $this->user);

    expect($scheme)->toBe(VatScheme::Kleinunternehmer);
});

it('calculates zero VAT for Kleinunternehmer invoices', function () {
    $invoice = Invoice::factory()->create([
        'vat_scheme' => VatScheme::Kleinunternehmer,
        'subtotal' => 1000.00,
    ]);

    $invoice->calculateTotals();

    expect($invoice->vat_amount)->toBe(0.00);
    expect($invoice->total)->toBe(1000.00);
});

it('includes legal notice on PDF for Kleinunternehmer', function () {
    $invoice = Invoice::factory()->create([
        'vat_scheme' => VatScheme::Kleinunternehmer,
    ]);

    expect($invoice->legal_notice_text)
        ->toContain('§ 19 UStG');
});
```
