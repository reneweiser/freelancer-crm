# Iteration 4: German Tax Compliance

## Overview

Implement comprehensive German tax law compliance features to ensure the CRM meets all legal requirements for freelancers and small businesses, including GoBD-compliant audit logging, VAT scheme handling, expense tracking for EÜR, and ZUGFeRD e-invoice export.

## Goals

1. **Legal Compliance** - Meet § 14 UStG invoice requirements and GoBD bookkeeping standards
2. **VAT Scheme Flexibility** - Support Kleinunternehmer, standard VAT, EU reverse charge, and non-EU exports
3. **Expense Tracking** - Full expense management with EÜR category mapping for annual tax filing
4. **E-Invoice Ready** - ZUGFeRD 2.1 export format (mandatory by Jan 2028, recommended by 2027)
5. **Audit Trail** - Immutable change history for all financial documents

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           German Tax Compliance                              │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────────────────┐  │
│  │   VAT Schemes   │  │  Audit Logging  │  │      Expense Tracking       │  │
│  ├─────────────────┤  ├─────────────────┤  ├─────────────────────────────┤  │
│  │ • Kleinuntern.  │  │ • All changes   │  │ • Categories (EÜR mapped)   │  │
│  │ • Standard 19%  │  │ • Timestamps    │  │ • Receipt upload            │  │
│  │ • Reduced 7%    │  │ • User tracking │  │ • VAT tracking (Vorsteuer)  │  │
│  │ • Reverse Charge│  │ • Old/new values│  │ • Payment date tracking     │  │
│  │ • Export (0%)   │  │ • GoBD compliant│  │ • Supplier management       │  │
│  └─────────────────┘  └─────────────────┘  └─────────────────────────────┘  │
│                                                                             │
│  ┌─────────────────────────────────┐  ┌─────────────────────────────────┐  │
│  │      E-Invoice Export           │  │     Document Retention          │  │
│  ├─────────────────────────────────┤  ├─────────────────────────────────┤  │
│  │ • ZUGFeRD 2.1 (PDF + XML)       │  │ • 8-year retention tracking     │  │
│  │ • EN 16931 compliant            │  │ • Deletion protection           │  │
│  │ • XRechnung-compatible profile  │  │ • Archive export for backup     │  │
│  └─────────────────────────────────┘  └─────────────────────────────────┘  │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Primary Use Cases

### UC1: Kleinunternehmer Invoice
Freelancer with <€22k revenue creates invoice without VAT, automatically includes § 19 UStG notice.

### UC2: EU Reverse Charge Invoice
Freelancer invoices EU client with valid VAT ID → System auto-detects reverse charge, adds required text.

### UC3: Expense Recording
Freelancer uploads receipt → System extracts/suggests category, tracks Vorsteuer for VAT returns.

### UC4: E-Invoice Export
Client requests e-invoice → System generates ZUGFeRD 2.1 PDF with embedded XML for automated processing.

### UC5: Tax Audit Preparation
Tax advisor requests records → Export all invoices, expenses, and audit logs for specified period.

## Scope

### In Scope
- VAT scheme enum with auto-detection logic
- Per-invoice VAT scheme selection (user-configurable)
- Service delivery date field (Leistungsdatum)
- GoBD-compliant audit logging for invoices and expenses
- Full expense tracking with:
  - EÜR category mapping (Anlage EÜR categories)
  - Receipt file upload
  - Supplier/vendor management
  - Vorsteuer (input VAT) tracking
- ZUGFeRD 2.1 e-invoice generation
- Document retention warnings and protection
- Filament UI for all features

### Out of Scope
- XRechnung pure XML format (B2G only, not needed for freelancers)
- DATEV export format (consider for future iteration)
- ELSTER direct submission
- Automated VAT return preparation
- Multi-currency support (EUR only)
- Credit note (Gutschrift) handling (consider for future)

## Success Criteria

1. Invoices pass German tax compliance validation (all Pflichtangaben present)
2. VAT scheme correctly applied based on client country + VAT ID
3. Audit log captures all changes to invoices with old/new values
4. Expenses categorized correctly for EÜR filing
5. ZUGFeRD 2.1 export validates against EN 16931 schema
6. Documents within retention period cannot be deleted
7. All features have >85% test coverage

## Dependencies

**Packages:**
- `horstoeko/zugferd` - ZUGFeRD/XRechnung PHP library
- `spatie/laravel-activitylog` - Audit logging (GoBD-compliant)
- `intervention/image` - Receipt image processing (optional)

**Existing:**
- DomPDF for PDF generation (already installed)
- Filament 4 for admin UI (already installed)
- Laravel Storage for file uploads (already configured)

## Implementation Phases

| Phase | Description | Priority |
|-------|-------------|----------|
| 1. Foundation | Audit logging, VAT scheme enum, data model changes | High |
| 2. Invoice Compliance | Service date, VAT scheme UI, reverse charge detection | High |
| 3. Expense Tracking | Expense model, categories, receipt upload, EÜR mapping | High |
| 4. E-Invoice Export | ZUGFeRD 2.1 generation, validation | Medium |
| 5. Retention & Archive | Deletion protection, retention tracking, export | Medium |

## Technical Decisions

- **Audit Logging:** Spatie Activity Log with custom properties for old/new values
- **VAT Scheme Storage:** Per-invoice enum field with user-level default
- **Reverse Charge Detection:** Auto-detect from client.country + client.vat_id presence
- **Expense Categories:** PHP enum mapped to official EÜR line numbers
- **File Storage:** Local disk with configurable retention, consider S3 for production
- **ZUGFeRD Profile:** "XRechnung" profile for maximum compatibility

## Timeline Considerations

| Deadline | Requirement |
|----------|-------------|
| Jan 2025 | Must receive e-invoices (already past) |
| Jan 2027 | Must issue e-invoices if turnover > €800k |
| Jan 2028 | All businesses must issue e-invoices |

**Recommendation:** Complete Phase 4 (E-Invoice Export) by Q3 2026 to allow testing before 2027 mandate.
