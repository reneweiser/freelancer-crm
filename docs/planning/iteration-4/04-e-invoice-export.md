# ZUGFeRD E-Invoice Export

## Overview

Implement ZUGFeRD 2.1 e-invoice export to comply with the German e-invoicing mandate (mandatory for all B2B transactions by January 2028). ZUGFeRD combines a human-readable PDF with embedded structured XML data, making it compatible with both manual and automated processing.

## ZUGFeRD Background

### What is ZUGFeRD?

**ZUGFeRD** (Zentraler User Guide des Forums elektronische Rechnung Deutschland) is a German e-invoice standard that embeds structured XML data (following EN 16931) into a PDF/A-3 document.

**Key Features:**
- PDF/A-3 for human readability
- Embedded XML for machine processing
- EN 16931 compliant (European standard)
- XRechnung-compatible profile available

### Profiles

| Profile | Use Case | XML Completeness |
|---------|----------|------------------|
| Minimum | Basic archiving | Minimal data |
| Basic | Simple invoices | Core fields only |
| EN 16931 (Comfort) | Standard B2B | Full compliance |
| **XRechnung** | B2G + full B2B | Maximum compatibility |

**We'll use the "XRechnung" profile** for maximum compatibility.

## Package Selection

**horstoeko/zugferd** (v1.x)

- Pure PHP, no external dependencies
- Creates and reads ZUGFeRD/XRechnung documents
- Validates against EN 16931 schema
- Actively maintained, German developer
- Supports all ZUGFeRD 2.x profiles

### Installation

```bash
sail composer require horstoeko/zugferd
```

## Implementation

### ZugferdService

```php
namespace App\Services;

use App\Models\Invoice;
use App\Enums\VatScheme;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdDocumentPdfBuilder;
use horstoeko\zugferd\ZugferdProfiles;
use horstoeko\zugferd\codelists\ZugferdPaymentMeans;
use horstoeko\zugferd\codelists\ZugferdUnitCodes;
use horstoeko\zugferd\codelists\ZugferdCountryCodes;
use horstoeko\zugferd\codelists\ZugferdCurrencyCodes;

class ZugferdService
{
    public function __construct(
        protected SettingsService $settings,
        protected PdfService $pdfService,
    ) {}

    /**
     * Generate ZUGFeRD PDF with embedded XML
     */
    public function generateZugferdPdf(Invoice $invoice): string
    {
        // First, generate the visual PDF using existing service
        $pdfContent = $this->pdfService->generateInvoicePdfContent($invoice);

        // Build ZUGFeRD XML document
        $document = $this->buildZugferdDocument($invoice);

        // Combine PDF and XML into ZUGFeRD PDF/A-3
        $zugferdPdf = ZugferdDocumentPdfBuilder::fromPdfContent($pdfContent)
            ->setDocument($document)
            ->generateDocument()
            ->downloadContent();

        return $zugferdPdf;
    }

    /**
     * Build the ZUGFeRD XML document
     */
    protected function buildZugferdDocument(Invoice $invoice): ZugferdDocumentBuilder
    {
        $user = $invoice->user;
        $client = $invoice->client;

        $document = ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_XRECHNUNG_3);

        // Document Information
        $document
            ->setDocumentInformation(
                $invoice->number,                    // Invoice number
                '380',                               // Type code: 380 = Invoice
                $invoice->issued_at,                 // Issue date
                ZugferdCurrencyCodes::EUR
            )
            ->addDocumentNote($invoice->notes ?? '')
            ->setDocumentSupplyChainEvent($invoice->service_date ?? $invoice->issued_at);

        // Seller (Your Business)
        $this->addSellerInformation($document, $user);

        // Buyer (Client)
        $this->addBuyerInformation($document, $client);

        // Payment Terms
        $this->addPaymentTerms($document, $invoice, $user);

        // Line Items
        foreach ($invoice->items as $index => $item) {
            $this->addLineItem($document, $item, $index + 1);
        }

        // Tax Summary
        $this->addTaxSummary($document, $invoice);

        // Document Totals
        $document->setDocumentSummation(
            $invoice->total,      // Grand total
            $invoice->total,      // Due payable amount
            $invoice->subtotal,   // Line total
            0.00,                 // Charge total
            0.00,                 // Allowance total
            $invoice->subtotal,   // Tax basis total
            $invoice->vat_amount, // Tax total
            0.00,                 // Prepaid amount
            0.00                  // Rounding amount
        );

        return $document;
    }

    /**
     * Add seller (business) information
     */
    protected function addSellerInformation(ZugferdDocumentBuilder $document, User $user): void
    {
        $settings = $user->settings();

        $document
            ->setDocumentSeller($settings->get('business_name'))
            ->setDocumentSellerAddress(
                $this->parseStreet($settings->get('business_address')),
                '',                                          // Line 2
                '',                                          // Line 3
                $this->parsePostalCode($settings->get('business_address')),
                $this->parseCity($settings->get('business_address')),
                ZugferdCountryCodes::DE
            )
            ->setDocumentSellerContact(
                $settings->get('contact_name') ?? $user->name,
                '',                                          // Department
                $settings->get('business_phone'),
                '',                                          // Fax
                $settings->get('business_email')
            );

        // Tax Registration
        $taxNumber = $settings->get('tax_number');
        $vatId = $settings->get('vat_id');

        if ($vatId) {
            $document->addDocumentSellerTaxRegistration('VA', $vatId); // VAT ID
        }
        if ($taxNumber) {
            $document->addDocumentSellerTaxRegistration('FC', $taxNumber); // Tax number
        }
    }

    /**
     * Add buyer (client) information
     */
    protected function addBuyerInformation(ZugferdDocumentBuilder $document, $client): void
    {
        $name = $client->company_name ?? $client->contact_name;

        $document
            ->setDocumentBuyer($name)
            ->setDocumentBuyerAddress(
                $client->street ?? '',
                '',
                '',
                $client->postal_code ?? '',
                $client->city ?? '',
                $this->mapCountryCode($client->country)
            );

        if ($client->vat_id) {
            $document->addDocumentBuyerTaxRegistration('VA', $client->vat_id);
        }

        if ($client->email) {
            $document->setDocumentBuyerContact(
                $client->contact_name ?? $name,
                '',
                $client->phone ?? '',
                '',
                $client->email
            );
        }
    }

    /**
     * Add payment terms and bank details
     */
    protected function addPaymentTerms(ZugferdDocumentBuilder $document, Invoice $invoice, User $user): void
    {
        $settings = $user->settings();

        // Payment due date
        $document->addDocumentPaymentTerm(
            sprintf('Zahlbar bis %s', $invoice->due_at->format('d.m.Y')),
            $invoice->due_at
        );

        // Bank details for SEPA transfer
        $iban = $settings->get('iban');
        $bic = $settings->get('bic');

        if ($iban) {
            $document->addDocumentPaymentMean(
                ZugferdPaymentMeans::SEPA_CREDIT_TRANSFER,
                null,
                null,
                null,
                null,
                null,
                $iban,
                $settings->get('business_name'),
                null,
                $bic
            );
        }
    }

    /**
     * Add a line item
     */
    protected function addLineItem(ZugferdDocumentBuilder $document, $item, int $position): void
    {
        $lineTotal = $item->quantity * $item->unit_price;

        $document
            ->addNewPosition((string) $position)
            ->setDocumentPositionProductDetails(
                $item->description,              // Name
                $item->description,              // Description
                null,                            // Seller ID
                null,                            // Buyer ID
                null                             // Global ID
            )
            ->setDocumentPositionNetPrice($item->unit_price)
            ->setDocumentPositionQuantity(
                $item->quantity,
                $this->mapUnitCode($item->unit)
            )
            ->addDocumentPositionTax(
                $this->mapTaxCategory($item),
                'VAT',
                $item->vat_rate
            )
            ->setDocumentPositionLineSummation($lineTotal);
    }

    /**
     * Add tax summary based on VAT scheme
     */
    protected function addTaxSummary(ZugferdDocumentBuilder $document, Invoice $invoice): void
    {
        $vatScheme = $invoice->vat_scheme;
        $taxCategory = $this->mapVatSchemeToCategory($vatScheme);

        $document->addDocumentTax(
            $taxCategory,
            'VAT',
            $invoice->subtotal,
            $invoice->vat_amount,
            $invoice->vat_rate
        );

        // Add exemption reason for zero-rate schemes
        if ($invoice->vat_rate == 0 && $vatScheme) {
            $exemptionReason = match($vatScheme) {
                VatScheme::Kleinunternehmer => 'Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.',
                VatScheme::ReverseCharge => 'Reverse Charge - Steuerschuldnerschaft des Leistungsempfängers.',
                VatScheme::ExportNonEU => 'Steuerfreie Ausfuhrlieferung.',
                default => null,
            };

            if ($exemptionReason) {
                $document->addDocumentNote($exemptionReason);
            }
        }
    }

    /**
     * Map unit string to ZUGFeRD unit code
     */
    protected function mapUnitCode(string $unit): string
    {
        return match(strtolower($unit)) {
            'stück', 'stk', 'st' => ZugferdUnitCodes::C62,  // One (piece)
            'stunden', 'std', 'h' => ZugferdUnitCodes::HUR, // Hour
            'tage', 'tag' => ZugferdUnitCodes::DAY,         // Day
            'pauschal' => ZugferdUnitCodes::C62,            // Lump sum as piece
            'monat', 'monate' => ZugferdUnitCodes::MON,     // Month
            default => ZugferdUnitCodes::C62,
        };
    }

    /**
     * Map country code to ZUGFeRD country code
     */
    protected function mapCountryCode(string $country): string
    {
        return match(strtoupper($country)) {
            'DE', 'DEUTSCHLAND', 'GERMANY' => ZugferdCountryCodes::DE,
            'AT', 'ÖSTERREICH', 'AUSTRIA' => ZugferdCountryCodes::AT,
            'CH', 'SCHWEIZ', 'SWITZERLAND' => ZugferdCountryCodes::CH,
            'FR', 'FRANKREICH', 'FRANCE' => ZugferdCountryCodes::FR,
            'NL', 'NIEDERLANDE', 'NETHERLANDS' => ZugferdCountryCodes::NL,
            // Add more as needed
            default => $country,
        };
    }

    /**
     * Map VAT scheme to ZUGFeRD tax category
     */
    protected function mapVatSchemeToCategory(VatScheme $scheme): string
    {
        return match($scheme) {
            VatScheme::Standard => 'S',          // Standard rate
            VatScheme::Reduced => 'S',           // Standard rate (reduced)
            VatScheme::Kleinunternehmer => 'E',  // Exempt
            VatScheme::ReverseCharge => 'AE',    // Reverse charge
            VatScheme::ExportNonEU => 'G',       // Export outside EU
            VatScheme::ExemptEducation => 'E',   // Exempt
            default => 'S',
        };
    }

    /**
     * Map line item to tax category
     */
    protected function mapTaxCategory($item): string
    {
        if ($item->vat_rate == 0) {
            return 'E'; // Exempt
        }
        return 'S'; // Standard
    }

    // Address parsing helpers

    protected function parseStreet(string $address): string
    {
        $lines = explode("\n", $address);
        return $lines[0] ?? '';
    }

    protected function parsePostalCode(string $address): string
    {
        if (preg_match('/(\d{5})/', $address, $matches)) {
            return $matches[1];
        }
        return '';
    }

    protected function parseCity(string $address): string
    {
        if (preg_match('/\d{5}\s+(.+)/', $address, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }
}
```

### Validation Service

```php
namespace App\Services;

use horstoeko\zugferd\ZugferdDocumentReader;
use horstoeko\zugferd\ZugferdDocumentValidator;

class ZugferdValidationService
{
    /**
     * Validate a ZUGFeRD document against EN 16931 schema
     */
    public function validate(string $xmlContent): ValidationResult
    {
        $reader = ZugferdDocumentReader::readAndGuessFromContent($xmlContent);
        $validator = new ZugferdDocumentValidator($reader);

        $errors = [];
        $warnings = [];

        // Validate against schema
        if (!$validator->validateXml()) {
            $errors = array_merge($errors, $validator->getXmlErrors());
        }

        // Validate business rules
        if (!$validator->validateAgainstProfile()) {
            $errors = array_merge($errors, $validator->getProfileErrors());
        }

        return new ValidationResult(
            isValid: empty($errors),
            errors: $errors,
            warnings: $warnings
        );
    }

    /**
     * Extract XML from ZUGFeRD PDF for validation
     */
    public function extractXmlFromPdf(string $pdfContent): ?string
    {
        try {
            $reader = ZugferdDocumentReader::readAndGuessFromContent($pdfContent);
            return $reader->getContent();
        } catch (\Exception $e) {
            return null;
        }
    }
}
```

## Filament Integration

### Download Action

Add to InvoiceResource Edit page:

```php
namespace App\Filament\Resources\Invoices\Pages;

use App\Services\ZugferdService;
use Filament\Actions;
use Filament\Notifications\Notification;

class EditInvoice extends EditRecord
{
    protected function getHeaderActions(): array
    {
        return [
            // Existing PDF action
            Actions\Action::make('downloadPdf')
                ->label('PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->action(fn () => $this->downloadPdf()),

            // New ZUGFeRD action
            Actions\Action::make('downloadZugferd')
                ->label('E-Rechnung (ZUGFeRD)')
                ->icon('heroicon-o-document-check')
                ->color('success')
                ->action(fn () => $this->downloadZugferd())
                ->visible(fn () => $this->record->status !== InvoiceStatus::Draft),

            // ... other actions
        ];
    }

    protected function downloadZugferd(): StreamedResponse
    {
        $service = app(ZugferdService::class);

        try {
            $content = $service->generateZugferdPdf($this->record);

            return response()->streamDownload(
                fn () => print($content),
                "Rechnung-{$this->record->number}-ZUGFeRD.pdf",
                ['Content-Type' => 'application/pdf']
            );
        } catch (\Exception $e) {
            Notification::make()
                ->title('Fehler beim Erstellen der E-Rechnung')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return back();
        }
    }
}
```

### Bulk Export Action

```php
// In ListInvoices page

Tables\Actions\BulkAction::make('exportZugferd')
    ->label('E-Rechnungen exportieren')
    ->icon('heroicon-o-document-check')
    ->action(function (Collection $records) {
        $service = app(ZugferdService::class);
        $zip = new \ZipArchive();
        $tempFile = tempnam(sys_get_temp_dir(), 'zugferd_');

        $zip->open($tempFile, \ZipArchive::CREATE);

        foreach ($records as $invoice) {
            if ($invoice->status === InvoiceStatus::Draft) {
                continue;
            }

            try {
                $pdf = $service->generateZugferdPdf($invoice);
                $filename = "Rechnung-{$invoice->number}-ZUGFeRD.pdf";
                $zip->addFromString($filename, $pdf);
            } catch (\Exception $e) {
                // Log error, continue with others
                logger()->error("ZUGFeRD export failed for {$invoice->number}", [
                    'error' => $e->getMessage()
                ]);
            }
        }

        $zip->close();

        return response()->download($tempFile, 'E-Rechnungen.zip')
            ->deleteFileAfterSend();
    })
    ->requiresConfirmation()
    ->deselectRecordsAfterCompletion(),
```

## Settings Page Addition

```php
// In Settings.php

Forms\Components\Section::make('E-Rechnung')
    ->description('Einstellungen für elektronische Rechnungen (ZUGFeRD/XRechnung)')
    ->schema([
        Forms\Components\Toggle::make('zugferd_enabled')
            ->label('ZUGFeRD aktivieren')
            ->helperText('E-Rechnungen im ZUGFeRD 2.1 Format generieren')
            ->default(true),

        Forms\Components\Select::make('zugferd_profile')
            ->label('ZUGFeRD-Profil')
            ->options([
                'xrechnung' => 'XRechnung (empfohlen)',
                'en16931' => 'EN 16931 (Comfort)',
                'basic' => 'Basic',
            ])
            ->default('xrechnung')
            ->helperText('XRechnung ist für maximale Kompatibilität empfohlen'),

        Forms\Components\Toggle::make('zugferd_auto_validate')
            ->label('Automatische Validierung')
            ->helperText('E-Rechnungen vor dem Download validieren')
            ->default(true),
    ]),
```

## PDF Template Adjustments

For ZUGFeRD PDF/A-3 compliance, the existing PDF needs minor adjustments:

```php
// In PdfService.php

public function generateInvoicePdfContent(Invoice $invoice): string
{
    $settings = $this->settings->getAll();

    $pdf = PDF::loadView('pdf.invoice', [
        'invoice' => $invoice,
        'business' => $this->getBusinessData($settings),
    ]);

    // Set PDF/A mode for ZUGFeRD compatibility
    $pdf->setOption('enable-local-file-access', true);
    $pdf->setOption('dpi', 300);

    return $pdf->output();
}
```

## Test Cases

```php
// tests/Feature/ZugferdTest.php

it('generates valid ZUGFeRD PDF', function () {
    $invoice = Invoice::factory()
        ->has(InvoiceItem::factory()->count(3))
        ->create([
            'status' => InvoiceStatus::Sent,
            'vat_scheme' => VatScheme::Standard,
        ]);

    $service = app(ZugferdService::class);
    $pdf = $service->generateZugferdPdf($invoice);

    expect($pdf)->not->toBeEmpty();

    // Verify PDF/A-3 structure
    expect($pdf)->toContain('%PDF-');
});

it('embeds valid XML in ZUGFeRD PDF', function () {
    $invoice = Invoice::factory()->create();

    $service = app(ZugferdService::class);
    $pdf = $service->generateZugferdPdf($invoice);

    $validationService = app(ZugferdValidationService::class);
    $xml = $validationService->extractXmlFromPdf($pdf);

    expect($xml)->not->toBeNull();
    expect($xml)->toContain('CrossIndustryInvoice');
});

it('validates against EN 16931 schema', function () {
    $invoice = Invoice::factory()
        ->has(InvoiceItem::factory()->count(2))
        ->create([
            'vat_scheme' => VatScheme::Standard,
        ]);

    $service = app(ZugferdService::class);
    $pdf = $service->generateZugferdPdf($invoice);

    $validationService = app(ZugferdValidationService::class);
    $xml = $validationService->extractXmlFromPdf($pdf);
    $result = $validationService->validate($xml);

    expect($result->isValid)->toBeTrue();
});

it('includes reverse charge notice for EU invoices', function () {
    $client = Client::factory()->create([
        'country' => 'FR',
        'vat_id' => 'FR12345678901',
    ]);

    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'vat_scheme' => VatScheme::ReverseCharge,
        'vat_rate' => 0,
    ]);

    $service = app(ZugferdService::class);
    $pdf = $service->generateZugferdPdf($invoice);

    $validationService = app(ZugferdValidationService::class);
    $xml = $validationService->extractXmlFromPdf($pdf);

    expect($xml)->toContain('AE'); // Reverse charge tax category
});

it('maps German units to ZUGFeRD codes', function () {
    $service = app(ZugferdService::class);

    expect($service->mapUnitCode('Stunden'))->toBe('HUR');
    expect($service->mapUnitCode('Tage'))->toBe('DAY');
    expect($service->mapUnitCode('Stück'))->toBe('C62');
});
```

## Migration Path

1. **Phase 1**: Install `horstoeko/zugferd` package
2. **Phase 2**: Implement `ZugferdService` with basic document generation
3. **Phase 3**: Add download action to invoice edit page
4. **Phase 4**: Add validation service and auto-validation
5. **Phase 5**: Add bulk export functionality
6. **Phase 6**: Add settings for profile selection

## Compliance Timeline

| Date | Requirement | Our Status |
|------|-------------|------------|
| Jan 2025 | Receive e-invoices | N/A (sending only) |
| Jan 2027 | Issue e-invoices (>€800k) | Ready |
| Jan 2028 | Issue e-invoices (all) | Ready |
