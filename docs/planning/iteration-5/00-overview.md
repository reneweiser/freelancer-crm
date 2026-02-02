# Iteration 5: Bank Integration & Payment Automation

## Overview

Integrate German bank accounts via the FinTS protocol to automatically detect incoming payments, match them to unpaid invoices, and send automated payment reminders to clients with overdue invoices.

## Goals

1. **Automatic Payment Detection** - Connect to bank via FinTS, import transactions, match to invoices
2. **Invoice Reconciliation** - Match payments by invoice number, amount, and client IBAN
3. **Payment Reminders** - Send automated email reminders for overdue invoices
4. **Fallback Import** - Support CSV/CAMT.053 file import when FinTS unavailable

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                      Bank Integration & Payment Automation                   │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────────────────┐  │
│  │  FinTS Adapter  │  │  CSV/CAMT Parser│  │    Payment Matching         │  │
│  ├─────────────────┤  ├─────────────────┤  ├─────────────────────────────┤  │
│  │ • Bank connect  │  │ • MT940 format  │  │ • Invoice # (HIGH conf.)   │  │
│  │ • Statement     │  │ • CAMT.053 XML  │  │ • Amount+IBAN (MEDIUM)     │  │
│  │   retrieval     │  │ • Bank CSV      │  │ • Amount only (LOW)        │  │
│  │ • Encrypted     │  │ • Fallback      │  │ • User confirmation        │  │
│  │   credentials   │  │   import        │  │ • Auto-mark paid           │  │
│  └─────────────────┘  └─────────────────┘  └─────────────────────────────┘  │
│                                                                             │
│  ┌─────────────────────────────────┐  ┌─────────────────────────────────┐  │
│  │    Transaction Storage          │  │     Payment Reminders           │  │
│  ├─────────────────────────────────┤  ├─────────────────────────────────┤  │
│  │ • Normalized transaction table  │  │ • Configurable days overdue    │  │
│  │ • Deduplication via unique ID   │  │ • Configurable interval        │  │
│  │ • Source tracking (fints/csv)   │  │ • Email to client              │  │
│  │ • Raw data preservation         │  │ • Reminder history tracking    │  │
│  └─────────────────────────────────┘  └─────────────────────────────────┘  │
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │                     Dashboard Widget                                  │   │
│  │    "3 Zahlungen zur Bestätigung" → [Bestätigen] [Ablehnen]           │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Primary Use Cases

### UC1: Automatic Payment Detection
Bank sync runs twice daily → Fetches new transactions → Matches credits to unpaid invoices → User confirms matches in dashboard widget.

### UC2: Payment Confirmation
User reviews pending match → Sees transaction details and matched invoice → Confirms → Invoice auto-marked as paid.

### UC3: Automated Payment Reminder
Invoice overdue > 3 days → System sends professional reminder email to client → Logs reminder → Repeats after 7 days if still unpaid.

### UC4: Manual CSV Import
FinTS unavailable → User exports bank statement as CSV → Uploads in Filament → Same matching workflow applies.

## Scope

### In Scope
- FinTS 3.0 bank connection (most German banks)
- Encrypted credential storage
- Transaction import and normalization
- Multi-strategy payment matching algorithm
- Dashboard widget for match confirmation
- Automated payment reminder emails
- CSV/CAMT.053 fallback import
- Configurable reminder settings

### Out of Scope
- Real-time bank webhooks (FinTS doesn't support this)
- SEPA direct debit initiation
- Multi-currency support (EUR only)
- Partial payment matching
- Cash flow forecasting (future iteration)

## Success Criteria

1. FinTS connection works with major German banks (Sparkasse, Volksbank, etc.)
2. Payment matching achieves >90% accuracy for invoice-number matches
3. False positive rate <5% for medium/low confidence matches
4. Automated reminders sent within 1 hour of scheduled time
5. All sensitive data encrypted at rest
6. Feature has >85% test coverage

## Dependencies

**Packages:**
- `abiturma/laravel-fints` - Laravel wrapper for FinTS (or `nemiah/php-fints`)

**External:**
- Deutsche Kreditwirtschaft registration (Product ID required, 2-8 week wait)
- Bank FinTS endpoint URLs (from hbci-zka.de database)

**Existing:**
- Invoice model with number, total, status, client relation
- Client model (will add IBAN field)
- Email system with templates
- Settings service for user configuration

## Implementation Phases

| Phase | Description | Priority |
|-------|-------------|----------|
| 1. Data Foundation | Migrations, models, enums | High |
| 2. Services | Import, matching, reminder services | High |
| 3. Scheduled Jobs | Bank sync, reminder dispatch | High |
| 4. Filament UI | Dashboard widget, settings, CSV import | High |
| 5. Testing | Feature tests for all services | High |
| 6. Deployment | DK registration, production setup | Medium |

## Technical Decisions

- **FinTS Library:** `abiturma/laravel-fints` for Laravel-native integration
- **Credential Storage:** Laravel `encrypted` cast on model attributes
- **Transaction ID:** Hash of bank ID + booking date + amount for deduplication
- **Matching Algorithm:** Three-tier confidence (HIGH/MEDIUM/LOW) with mandatory confirmation
- **Reminder Logic:** Days-since-due threshold + minimum interval between reminders
- **Sync Schedule:** Twice daily (7:00 AM, 5:00 PM) to balance freshness vs. API calls

## Timeline Considerations

| Milestone | Requirement |
|-----------|-------------|
| Week 1-2 | Submit DK registration (mandatory wait begins) |
| Week 2-4 | Implement data model and services |
| Week 4-6 | Build Filament UI and tests |
| Week 6-10 | Wait for DK registration propagation |
| Week 10+ | Production testing with real bank |

**Note:** CSV import can be used immediately; FinTS requires DK registration.

## Documents

- [01-bank-integration.md](./01-bank-integration.md) - Full design specification
