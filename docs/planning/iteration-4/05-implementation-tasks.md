# Implementation Tasks

## Overview

Detailed task breakdown for implementing German Tax Compliance features in iteration-4.

## Phase 1: Foundation

### 1.1 Install Dependencies
- [ ] Run `sail composer require spatie/laravel-activitylog`
- [ ] Run `sail composer require horstoeko/zugferd`
- [ ] Publish activity log migrations: `sail artisan vendor:publish --tag=activitylog-migrations`
- [ ] Run migrations: `sail artisan migrate`

**Files:**
- `composer.json`
- `database/migrations/*_create_activity_log_table.php`

### 1.2 Create VatScheme Enum
- [ ] Create `app/Enums/VatScheme.php`
- [ ] Implement `getLabel()`, `getColor()`, `getVatRate()`, `getInvoiceNotice()`
- [ ] Add EU countries list constant
- [ ] Write enum tests

**Files:**
- `app/Enums/VatScheme.php`
- `tests/Unit/VatSchemeTest.php`

### 1.3 Create ExpenseCategory Enum
- [ ] Create `app/Enums/ExpenseCategory.php`
- [ ] Implement `getLabel()`, `getColor()`, `getEurLineNumber()`
- [ ] Add VAT deductibility rules
- [ ] Write enum tests

**Files:**
- `app/Enums/ExpenseCategory.php`
- `tests/Unit/ExpenseCategoryTest.php`

### 1.4 Create Database Migrations
- [ ] Create migration for `invoices` table additions (`vat_scheme`, `service_date`, `legal_notice`)
- [ ] Create migration for `clients` table (`tax_number` field)
- [ ] Create migration for `suppliers` table
- [ ] Create migration for `expenses` table
- [ ] Create migration to add GoBD fields to `activity_log` table
- [ ] Run migrations: `sail artisan migrate`

**Files:**
- `database/migrations/2026_XX_XX_add_vat_scheme_to_invoices.php`
- `database/migrations/2026_XX_XX_add_tax_number_to_clients.php`
- `database/migrations/2026_XX_XX_create_suppliers_table.php`
- `database/migrations/2026_XX_XX_create_expenses_table.php`
- `database/migrations/2026_XX_XX_add_gobd_fields_to_activity_log.php`

### 1.5 Configure Activity Log
- [ ] Publish config: `sail artisan vendor:publish --tag=activitylog-config`
- [ ] Set retention to 10 years
- [ ] Create custom `Activity` model with update/delete protection
- [ ] Register custom model in config

**Files:**
- `config/activitylog.php`
- `app/Models/Activity.php`

---

## Phase 2: Invoice Compliance

### 2.1 Update Invoice Model
- [ ] Add `vat_scheme` cast to Invoice model
- [ ] Add `service_date` cast
- [ ] Implement `LogsActivity` trait
- [ ] Configure activity log options (fields to track)
- [ ] Add `getLegalNoticeTextAttribute()` accessor
- [ ] Update `calculateTotals()` to use VAT scheme rate
- [ ] Write model tests

**Files:**
- `app/Models/Invoice.php`
- `tests/Feature/InvoiceModelTest.php`

### 2.2 Update InvoiceItem Model
- [ ] Implement `LogsActivity` trait
- [ ] Configure activity log with invoice context
- [ ] Write model tests

**Files:**
- `app/Models/InvoiceItem.php`
- `tests/Feature/InvoiceItemModelTest.php`

### 2.3 Create VatSchemeDetector Service
- [ ] Create `app/Services/VatSchemeDetector.php`
- [ ] Implement EU country detection
- [ ] Implement reverse charge detection logic
- [ ] Implement Kleinunternehmer check
- [ ] Write service tests

**Files:**
- `app/Services/VatSchemeDetector.php`
- `tests/Feature/VatSchemeDetectorTest.php`

### 2.4 Create InvoiceComplianceValidator
- [ ] Create `app/Services/InvoiceComplianceValidator.php`
- [ ] Validate all § 14 UStG Pflichtangaben
- [ ] Validate reverse charge requirements
- [ ] Write validator tests

**Files:**
- `app/Services/InvoiceComplianceValidator.php`
- `tests/Feature/InvoiceComplianceValidatorTest.php`

### 2.5 Update InvoiceForm Schema
- [ ] Add `vat_scheme` select with reactive behavior
- [ ] Add `service_date` date picker
- [ ] Add `legal_notice` textarea with placeholder
- [ ] Update client selection to trigger VAT scheme suggestion
- [ ] Update VAT rate sync from scheme

**Files:**
- `app/Filament/Resources/Invoices/Schemas/InvoiceForm.php`

### 2.6 Update Client Model & Form
- [ ] Add `tax_number` field to Client model
- [ ] Update ClientForm to include tax_number
- [ ] Ensure vat_id visibility for companies

**Files:**
- `app/Models/Client.php`
- `app/Filament/Resources/Clients/Schemas/ClientForm.php`

### 2.7 Update Settings Page
- [ ] Add "Umsatzsteuer" section
- [ ] Add `is_kleinunternehmer` toggle
- [ ] Add `default_vat_scheme` select
- [ ] Update VAT ID visibility based on Kleinunternehmer status

**Files:**
- `app/Filament/Pages/Settings.php`

### 2.8 Update PDF Template
- [ ] Add service date display (Leistungsdatum)
- [ ] Add legal notice section
- [ ] Add reverse charge VAT ID display
- [ ] Update VAT section for zero-rate invoices

**Files:**
- `resources/views/pdf/invoice.blade.php`

---

## Phase 3: Expense Tracking

### 3.1 Create Supplier Model
- [ ] Create `app/Models/Supplier.php`
- [ ] Define fillable fields and casts
- [ ] Add relationships (user, expenses)
- [ ] Add scopes and accessors
- [ ] Create factory: `sail artisan make:factory SupplierFactory`

**Files:**
- `app/Models/Supplier.php`
- `database/factories/SupplierFactory.php`

### 3.2 Create Expense Model
- [ ] Create `app/Models/Expense.php`
- [ ] Define fillable fields and casts
- [ ] Implement `LogsActivity` trait
- [ ] Add relationships (user, supplier)
- [ ] Implement `calculateAmounts()` method
- [ ] Add `deductible_vat` accessor
- [ ] Add scopes (byYear, byCategory, paidInYear)
- [ ] Create factory: `sail artisan make:factory ExpenseFactory`

**Files:**
- `app/Models/Expense.php`
- `database/factories/ExpenseFactory.php`

### 3.3 Create SupplierResource
- [ ] Create resource: `sail artisan make:filament-resource Supplier --generate`
- [ ] Configure form schema (basic info, address, tax info, bank details)
- [ ] Configure table columns and filters
- [ ] Set navigation group to "Finanzen"

**Files:**
- `app/Filament/Resources/Suppliers/SupplierResource.php`
- `app/Filament/Resources/Suppliers/Pages/*.php`

### 3.4 Create ExpenseResource
- [ ] Create resource: `sail artisan make:filament-resource Expense --generate`
- [ ] Implement ExpenseForm schema (supplier select, amount, VAT, category, receipt upload)
- [ ] Implement ExpensesTable schema (columns, filters)
- [ ] Add receipt preview/download action
- [ ] Set navigation group to "Finanzen"

**Files:**
- `app/Filament/Resources/Expenses/ExpenseResource.php`
- `app/Filament/Resources/Expenses/Schemas/ExpenseForm.php`
- `app/Filament/Resources/Expenses/Schemas/ExpensesTable.php`
- `app/Filament/Resources/Expenses/Pages/*.php`

### 3.5 Create EurExportService
- [ ] Create `app/Services/EurExportService.php`
- [ ] Implement `generateSummary()` for year totals
- [ ] Implement `exportExpensesCsv()` for tax advisor
- [ ] Group by EÜR categories with line numbers
- [ ] Calculate Vorsteuer totals
- [ ] Write service tests

**Files:**
- `app/Services/EurExportService.php`
- `tests/Feature/EurExportServiceTest.php`

### 3.6 Create Expense Report Page
- [ ] Create Filament page for expense/income summary
- [ ] Show EÜR category breakdown
- [ ] Add year filter
- [ ] Add CSV export action
- [ ] Show Vorsteuer/Umsatzsteuer summary

**Files:**
- `app/Filament/Pages/TaxReport.php`
- `resources/views/filament/pages/tax-report.blade.php`

### 3.7 Write Expense Tests
- [ ] Test VAT calculation (19%, 7%, 0%)
- [ ] Test deductibility rules (entertainment 70%)
- [ ] Test EÜR grouping by category
- [ ] Test Abflussprinzip (payment date filtering)
- [ ] Test receipt upload and retrieval

**Files:**
- `tests/Feature/ExpenseTest.php`
- `tests/Feature/EurExportServiceTest.php`

---

## Phase 4: E-Invoice Export

### 4.1 Create ZugferdService
- [ ] Create `app/Services/ZugferdService.php`
- [ ] Implement `generateZugferdPdf()` method
- [ ] Implement `buildZugferdDocument()` with all sections
- [ ] Add seller/buyer information methods
- [ ] Add line item handling
- [ ] Add tax summary with exemption reasons
- [ ] Map units and countries to ZUGFeRD codes

**Files:**
- `app/Services/ZugferdService.php`

### 4.2 Create ZugferdValidationService
- [ ] Create `app/Services/ZugferdValidationService.php`
- [ ] Implement XML extraction from PDF
- [ ] Implement EN 16931 schema validation
- [ ] Return structured validation results

**Files:**
- `app/Services/ZugferdValidationService.php`
- `app/Support/ValidationResult.php`

### 4.3 Update PdfService for PDF/A
- [ ] Add `generateInvoicePdfContent()` method returning raw content
- [ ] Ensure PDF/A compatibility settings
- [ ] Add high DPI for quality

**Files:**
- `app/Services/PdfService.php`

### 4.4 Add ZUGFeRD Download Action
- [ ] Add "E-Rechnung (ZUGFeRD)" action to EditInvoice page
- [ ] Implement download with proper filename
- [ ] Add validation before download (optional)
- [ ] Show error notification on failure

**Files:**
- `app/Filament/Resources/Invoices/Pages/EditInvoice.php`

### 4.5 Add Bulk Export Action
- [ ] Add bulk action to ListInvoices page
- [ ] Generate ZIP with multiple ZUGFeRD PDFs
- [ ] Skip draft invoices
- [ ] Handle errors gracefully

**Files:**
- `app/Filament/Resources/Invoices/Pages/ListInvoices.php`

### 4.6 Add E-Invoice Settings
- [ ] Add "E-Rechnung" section to Settings page
- [ ] Add ZUGFeRD enable toggle
- [ ] Add profile selection (XRechnung, EN 16931, Basic)
- [ ] Add auto-validation toggle

**Files:**
- `app/Filament/Pages/Settings.php`

### 4.7 Write ZUGFeRD Tests
- [ ] Test PDF generation (not empty, valid structure)
- [ ] Test XML embedding
- [ ] Test EN 16931 validation
- [ ] Test reverse charge handling
- [ ] Test unit code mapping

**Files:**
- `tests/Feature/ZugferdServiceTest.php`
- `tests/Feature/ZugferdValidationServiceTest.php`

---

## Phase 5: Audit & Retention

### 5.1 Create AuditChecksumService
- [ ] Create `app/Services/AuditChecksumService.php`
- [ ] Implement checksum generation (SHA-256)
- [ ] Implement checksum verification

**Files:**
- `app/Services/AuditChecksumService.php`

### 5.2 Create AddAuditMetadata Listener
- [ ] Create `app/Listeners/AddAuditMetadata.php`
- [ ] Add IP address capture
- [ ] Add user agent capture
- [ ] Generate and store checksum
- [ ] Register listener in EventServiceProvider

**Files:**
- `app/Listeners/AddAuditMetadata.php`
- `app/Providers/EventServiceProvider.php`

### 5.3 Create AuditLogRelationManager
- [ ] Create relation manager for invoices
- [ ] Show timestamp, description, user, old/new values
- [ ] Add integrity verification column
- [ ] Configure pagination

**Files:**
- `app/Filament/Resources/Invoices/RelationManagers/AuditLogRelationManager.php`

### 5.4 Create Audit Log Page
- [ ] Create standalone audit log page
- [ ] Filter by log type (invoices, expenses, etc.)
- [ ] Filter by date range
- [ ] Show all user activities

**Files:**
- `app/Filament/Pages/AuditLog.php`
- `resources/views/filament/pages/audit-log.blade.php`

### 5.5 Create AuditExportService
- [ ] Create `app/Services/AuditExportService.php`
- [ ] Implement CSV export for date range
- [ ] Implement JSON export for machine processing
- [ ] Include integrity verification status

**Files:**
- `app/Services/AuditExportService.php`

### 5.6 Implement Retention Protection
- [ ] Add `retention_until` calculated attribute to Invoice
- [ ] Add deletion check in Invoice `deleting` event
- [ ] Add deletion check in Expense `deleting` event
- [ ] Show warning in Filament delete modal

**Files:**
- `app/Models/Invoice.php`
- `app/Models/Expense.php`

### 5.7 Write Audit Tests
- [ ] Test activity log creation on model changes
- [ ] Test old/new value capture
- [ ] Test checksum generation and verification
- [ ] Test update/delete protection on Activity model
- [ ] Test retention period calculation

**Files:**
- `tests/Feature/AuditLogTest.php`
- `tests/Feature/RetentionProtectionTest.php`

---

## Dependencies Between Tasks

```
Phase 1 (Foundation)
    │
    ├── 1.1 Dependencies ──┐
    │                      │
    ├── 1.2 VatScheme ─────┼── Phase 2 (Invoice Compliance)
    │                      │         │
    ├── 1.3 ExpenseCategory┼── Phase 3 (Expense Tracking)
    │                      │
    ├── 1.4 Migrations ────┘
    │
    └── 1.5 Activity Log ────── Phase 5 (Audit & Retention)

Phase 2 ──────┐
              │
Phase 3 ──────┼──────── Phase 4 (E-Invoice Export)
              │
Phase 5 ──────┘
```

## Estimated File Count

### Models & Enums

| Category | Files |
|----------|-------|
| Enums | 2 (VatScheme, ExpenseCategory) |
| Models | 3 (Supplier, Expense, Activity) |
| Factories | 2 (SupplierFactory, ExpenseFactory) |
| **Subtotal** | **7 files** |

### Services

| Category | Files |
|----------|-------|
| Tax Services | 3 (VatSchemeDetector, InvoiceComplianceValidator, EurExportService) |
| E-Invoice Services | 2 (ZugferdService, ZugferdValidationService) |
| Audit Services | 2 (AuditChecksumService, AuditExportService) |
| **Subtotal** | **7 files** |

### Filament

| Category | Files |
|----------|-------|
| Resources | 2 (SupplierResource, ExpenseResource) |
| Resource Pages | 6 (List, Create, Edit × 2) |
| Resource Schemas | 4 (forms and tables) |
| Relation Managers | 1 (AuditLogRelationManager) |
| Pages | 2 (AuditLog, TaxReport) |
| Views | 2 (page views) |
| **Subtotal** | **17 files** |

### Migrations

| Category | Files |
|----------|-------|
| Schema changes | 5 |
| **Subtotal** | **5 files** |

### Tests

| Category | Files |
|----------|-------|
| Unit tests | 2 (enums) |
| Feature tests | 8 (services, models) |
| **Subtotal** | **10 files** |

### Documentation

| Category | Files |
|----------|-------|
| Planning docs | 5 |
| **Subtotal** | **5 files** |

**Grand Total:** ~51 files

## Test Coverage Goals

| Area | Target |
|------|--------|
| VAT Scheme logic | 100% |
| Invoice compliance validation | 100% |
| Expense calculations | 100% |
| ZUGFeRD generation | 90% |
| Audit logging | 90% |
| Overall | >85% |
