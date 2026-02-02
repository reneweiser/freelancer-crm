# Bank Integration Feature Design

**Feature:** FinTS Bank Integration with Automatic Payment Matching
**Date:** 2026-02-02
**Status:** Planning

---

## Executive Summary

This feature integrates German bank accounts via the FinTS protocol to automatically detect incoming payments and match them to unpaid invoices. It also enables automated payment reminder emails to clients with overdue invoices.

---

## Benefits

### For the Freelancer

1. **Time Savings** - No more manually checking bank statements and cross-referencing with invoices
2. **Faster Cash Flow Visibility** - Know immediately when clients pay, twice daily
3. **Reduced Administrative Overhead** - Automatic matching eliminates tedious bookkeeping tasks
4. **Proactive Collection** - Automated reminders ensure overdue invoices don't slip through the cracks
5. **Accurate Records** - Payment dates are captured from actual bank transactions, not manual entry

### For Client Relationships

1. **Professional Communication** - Consistent, timely payment reminders
2. **Reduced Awkwardness** - Automated reminders remove the personal discomfort of asking for payment
3. **Clear Documentation** - Every reminder is logged for reference

### Technical Benefits

1. **Data Integrity** - Bank transaction data is authoritative
2. **Audit Trail** - Complete history of transactions, matches, and confirmations
3. **Flexibility** - Works with FinTS or manual CSV import as fallback
4. **Extensibility** - Foundation for future features (cash flow forecasting, tax prep)

---

## Use Cases

### UC-1: Automatic Payment Detection

**Actor:** System (scheduled job)
**Trigger:** Twice daily at 7:00 AM and 5:00 PM

**Flow:**
1. System connects to bank via FinTS
2. Retrieves new transactions since last sync
3. For each incoming payment (credit):
   - Searches for invoice number in transfer purpose (Verwendungszweck)
   - If found, creates HIGH confidence match
   - If not found, checks sender IBAN against known clients
   - If client match + exact amount, creates MEDIUM confidence match
   - If only amount matches single invoice, creates LOW confidence match
4. User receives Filament notification for each pending match

**Outcome:** Pending matches appear in dashboard widget for confirmation

---

### UC-2: Confirming a Payment Match

**Actor:** Freelancer
**Trigger:** User reviews pending match in dashboard widget

**Flow:**
1. User sees pending payment matches in dashboard widget
2. Reviews transaction details: date, amount, sender, purpose
3. Reviews matched invoice: number, client, total
4. Clicks "Bestätigen" (Confirm)
5. System:
   - Marks invoice as "Paid" with booking_date as paid_at
   - Sets payment_method to "Überweisung"
   - Records confirmation timestamp
   - Sends success notification

**Outcome:** Invoice status updated, match archived as confirmed

---

### UC-3: Rejecting a False Match

**Actor:** Freelancer
**Trigger:** User identifies incorrect match

**Flow:**
1. User reviews pending match
2. Determines payment is not for the suggested invoice
3. Clicks "Ablehnen" (Reject)
4. Optionally adds note explaining rejection
5. System marks match as rejected

**Outcome:** Transaction remains available for future matching; rejected match archived

---

### UC-4: Automated Payment Reminder

**Actor:** System (scheduled job)
**Trigger:** Daily at 8:00 AM

**Flow:**
1. System queries invoices with status "Sent" or "Overdue" past due_at
2. For each overdue invoice:
   - Check if days overdue >= configured threshold (default: 3 days)
   - Check if enough time passed since last reminder (default: 7 days)
   - If conditions met, dispatch reminder email job
3. Email sent to client with invoice details
4. Invoice reminder_count incremented, last_reminder_sent_at updated

**Outcome:** Client receives professional payment reminder email

---

### UC-5: Manual CSV Import (Fallback)

**Actor:** Freelancer
**Trigger:** FinTS unavailable or user prefers manual import

**Flow:**
1. User exports bank statement as CSV/CAMT.053 from online banking
2. Navigates to Bank Transactions page in Filament
3. Uploads file via import action
4. System parses and imports transactions
5. Matching service processes new transactions
6. User reviews matches as in UC-2

**Outcome:** Same matching workflow, different data source

---

### UC-6: Setting Up Bank Connection

**Actor:** Freelancer
**Trigger:** Initial setup or adding new bank account

**Flow:**
1. User navigates to Settings > Bankverbindung
2. Enters bank code (BLZ), FinTS username, PIN
3. Enables automatic synchronization toggle
4. System validates connection (test request)
5. On success, stores encrypted credentials
6. First sync runs immediately

**Outcome:** Bank connection active, ready for automatic sync

---

## Workflows

### Payment Lifecycle with Bank Integration

```
                          ┌─────────────────────────────────────┐
                          │         INVOICE CREATED             │
                          │        Status: Draft                │
                          └──────────────┬──────────────────────┘
                                         │
                                         ▼
                          ┌─────────────────────────────────────┐
                          │         INVOICE SENT                │
                          │        Status: Sent                 │
                          │        Email to client              │
                          └──────────────┬──────────────────────┘
                                         │
                    ┌────────────────────┼────────────────────┐
                    │                    │                    │
                    ▼                    ▼                    ▼
         ┌──────────────────┐  ┌─────────────────┐  ┌─────────────────┐
         │   Client Pays    │  │  Due Date       │  │  No Payment     │
         │   via Bank       │  │  Passes         │  │  Detected       │
         └────────┬─────────┘  └────────┬────────┘  └────────┬────────┘
                  │                     │                    │
                  ▼                     ▼                    │
         ┌──────────────────┐  ┌─────────────────┐           │
         │  FinTS Sync      │  │ Status: Overdue │◄──────────┘
         │  Detects Payment │  │ + Reminder      │
         └────────┬─────────┘  │   Created       │
                  │            └────────┬────────┘
                  ▼                     │
         ┌──────────────────┐           │    ┌─────────────────┐
         │  Matching        │           │    │ Automated       │
         │  Service Runs    │           │    │ Reminder Email  │
         └────────┬─────────┘           │    │ (after X days)  │
                  │                     │    └────────┬────────┘
                  ▼                     │             │
         ┌──────────────────┐           │             │
         │  Match Found     │           │             │
         │  (Pending)       │           │             │
         └────────┬─────────┘           │             │
                  │                     │             │
                  ▼                     │             │
         ┌──────────────────┐           │             │
         │  User Confirms   │◄──────────┴─────────────┘
         │  Match           │     (Payment eventually arrives)
         └────────┬─────────┘
                  │
                  ▼
         ┌──────────────────┐
         │  INVOICE PAID    │
         │  Status: Paid    │
         │  paid_at set     │
         │  payment_method: │
         │  "Überweisung"   │
         └──────────────────┘
```

### Transaction Matching Algorithm

```
┌─────────────────────────────────────────────────────────────────────┐
│                    INCOMING BANK TRANSACTION                        │
│   Amount: €1,190.00 | Sender: Max Mustermann GmbH                  │
│   IBAN: DE89370400440532013000                                     │
│   Purpose: "Zahlung Rechnung 2026-001"                             │
└─────────────────────────────┬───────────────────────────────────────┘
                              │
                              ▼
              ┌───────────────────────────────┐
              │  STEP 1: Parse Invoice Number │
              │  Regex: /\b(\d{4}-\d{3})\b/   │
              └───────────────┬───────────────┘
                              │
                    Found: "2026-001"
                              │
                              ▼
              ┌───────────────────────────────┐
              │  STEP 2: Lookup Invoice       │
              │  Invoice #2026-001 exists?    │
              │  Status: Sent/Overdue?        │
              │  Amount matches (€1,190.00)?  │
              └───────────────┬───────────────┘
                              │
                         All YES
                              │
                              ▼
              ┌───────────────────────────────┐
              │  CREATE MATCH                 │
              │  Confidence: HIGH             │
              │  Reason: invoice_number       │
              │  Status: Pending              │
              └───────────────────────────────┘


        ═══════════════════════════════════════════════════════


┌─────────────────────────────────────────────────────────────────────┐
│                    INCOMING BANK TRANSACTION                        │
│   Amount: €595.00 | Sender: Acme Corp                              │
│   IBAN: DE123456789                                                │
│   Purpose: "Vielen Dank"                                           │
└─────────────────────────────┬───────────────────────────────────────┘
                              │
                              ▼
              ┌───────────────────────────────┐
              │  STEP 1: Parse Invoice Number │
              │  No invoice number found      │
              └───────────────┬───────────────┘
                              │
                              ▼
              ┌───────────────────────────────┐
              │  STEP 2: Match by IBAN        │
              │  Client with IBAN exists?     │
              └───────────────┬───────────────┘
                              │
                     Client: "Acme Corp"
                              │
                              ▼
              ┌───────────────────────────────┐
              │  STEP 3: Find Unpaid Invoice  │
              │  From Acme Corp               │
              │  Amount = €595.00             │
              └───────────────┬───────────────┘
                              │
                         Found 1 match
                              │
                              ▼
              ┌───────────────────────────────┐
              │  CREATE MATCH                 │
              │  Confidence: MEDIUM           │
              │  Reason: amount_client        │
              │  Status: Pending              │
              └───────────────────────────────┘
```

### Reminder Escalation Timeline

```
Day 0: Invoice Due Date
      │
      ├─── Day 1-2: Grace period (no action)
      │
      ▼
Day 3: First Reminder Sent (configurable)
      │   Subject: "Zahlungserinnerung: Rechnung {number}"
      │   Tone: Friendly reminder
      │
      ├─── Day 4-9: Wait period
      │
      ▼
Day 10: Second Reminder Sent (Day 3 + 7 days interval)
      │   Subject: "Zweite Zahlungserinnerung: Rechnung {number}"
      │   Tone: Firmer reminder
      │
      ├─── Day 11-16: Wait period
      │
      ▼
Day 17: Third Reminder Sent
      │   Subject: "Letzte Zahlungserinnerung: Rechnung {number}"
      │   Tone: Final notice
      │
      └─── After: Manual follow-up required
```

---

## Requirements

### Functional Requirements

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-1 | Connect to German banks via FinTS 3.0 protocol | Must |
| FR-2 | Import bank transactions twice daily (7 AM, 5 PM) | Must |
| FR-3 | Match incoming payments to unpaid invoices by invoice number | Must |
| FR-4 | Match incoming payments by amount + client IBAN | Must |
| FR-5 | Display pending matches in dashboard widget | Must |
| FR-6 | Allow user to confirm or reject matches | Must |
| FR-7 | Auto-mark invoice as paid when match confirmed | Must |
| FR-8 | Send automated payment reminder emails | Must |
| FR-9 | Support CSV/CAMT.053 import as fallback | Should |
| FR-10 | Configure reminder timing (days overdue, interval) | Should |
| FR-11 | Track reminder history per invoice | Should |

### Non-Functional Requirements

| ID | Requirement | Metric |
|----|-------------|--------|
| NFR-1 | Bank credentials encrypted at rest | AES-256 via Laravel encryption |
| NFR-2 | FinTS sync completes within 30 seconds | Per connection |
| NFR-3 | Support 1000+ transactions per month | No performance degradation |
| NFR-4 | Match processing < 1 second per transaction | Average case |

### External Dependencies

| Dependency | Purpose | Status |
|------------|---------|--------|
| [abiturma/laravel-fints](https://packagist.org/packages/abiturma/laravel-fints) | FinTS client library | Available |
| Deutsche Kreditwirtschaft Registration | Required Product ID | ~2-8 weeks to obtain |
| Bank FinTS support | Not all banks equal | Most German banks supported |

---

## Data Model

### New Tables

#### `bank_connections`

Stores encrypted FinTS credentials per user.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| user_id | bigint | FK to users |
| name | string | Display name (e.g., "Geschäftskonto") |
| bank_code | string | BLZ |
| bank_name | string | Bank display name |
| username | text | Encrypted FinTS username |
| pin | text | Encrypted PIN |
| fints_url | string | FinTS server URL |
| account_number | string | Account number |
| iban | string | IBAN (unique per user) |
| is_active | boolean | Enable/disable sync |
| last_synced_at | timestamp | Last successful sync |
| last_sync_error_at | timestamp | Last failed sync |
| last_sync_error | text | Error message |

#### `bank_transactions`

Normalized transaction data from any source.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| user_id | bigint | FK to users |
| bank_connection_id | bigint | FK to bank_connections |
| transaction_id | string | Unique ID (bank ID or hash) |
| booking_date | date | Transaction date |
| value_date | date | Value date |
| amount | decimal(12,2) | Amount (+credit, -debit) |
| currency | string(3) | Currency code (EUR) |
| sender_name | string | Sender/receiver name |
| sender_iban | string | Sender/receiver IBAN |
| purpose | text | Verwendungszweck |
| raw_data | json | Original MT940/CAMT data |
| source | string | 'fints', 'csv', 'camt053' |

#### `payment_matches`

Tracks proposed and resolved matches.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| user_id | bigint | FK to users |
| bank_transaction_id | bigint | FK to bank_transactions |
| invoice_id | bigint | FK to invoices |
| status | enum | pending, confirmed, rejected |
| confidence | enum | high, medium, low |
| match_reason | string | invoice_number, amount_client, amount_only |
| matched_amount | decimal(12,2) | Amount matched |
| confirmed_at | timestamp | When confirmed |
| rejected_at | timestamp | When rejected |
| notes | text | User notes |

### Modified Tables

#### `clients` (add columns)

| Column | Type | Description |
|--------|------|-------------|
| iban | string | Client's bank IBAN |
| bic | string | Client's BIC |

#### `invoices` (add columns)

| Column | Type | Description |
|--------|------|-------------|
| reminder_count | integer | Number of reminders sent |
| last_reminder_sent_at | timestamp | Last reminder timestamp |

---

## Service Architecture

### Services

| Service | Responsibility |
|---------|----------------|
| `FintsService` | Wraps FinTS library, handles connection, retrieves statements |
| `TransactionImportService` | Normalizes transactions from FinTS/CSV/CAMT sources |
| `PaymentMatchingService` | Implements matching algorithm, creates/confirms/rejects matches |
| `PaymentReminderService` | Determines which invoices need reminders, dispatches emails |

### Jobs

| Job | Schedule | Purpose |
|-----|----------|---------|
| `SyncBankTransactions` | 7:00, 17:00 | Fetch new transactions via FinTS |
| `ProcessPaymentMatches` | After sync | Run matching on new credits |
| `SendPaymentReminders` | 8:00 daily | Email overdue invoice reminders |

### Events

| Event | Listeners |
|-------|-----------|
| `TransactionImported` | `MatchTransactionListener` |
| `PaymentMatchConfirmed` | `MarkInvoicePaidListener` |
| `InvoiceOverdue` | `CreateReminderListener` |

---

## Filament UI Components

### Dashboard Widget: Pending Payments

- Table widget showing all pending matches
- Columns: Date, Invoice #, Client, Amount, Confidence, Purpose
- Actions: Confirm (green), Reject (red)
- Only visible when pending matches exist
- Auto-refreshes every 30 seconds

### Settings Page: Bank Connection Section

- Bank code, username, PIN (password field)
- Enable/disable automatic sync toggle
- Test connection button
- Last sync status display
- Reminder settings (days overdue, interval)

### Bank Transactions Resource (optional)

- Read-only table of all imported transactions
- Filter by date range, matched/unmatched
- CSV import action in header

---

## Implementation Tasks

### Phase 1: Data Foundation

- [ ] **Create migrations** `priority:1` `phase:data`
  - bank_connections table
  - bank_transactions table
  - payment_matches table
  - Add iban/bic to clients
  - Add reminder fields to invoices

- [ ] **Create Eloquent models** `priority:1` `phase:data`
  - BankConnection (with encrypted casts)
  - BankTransaction (with scopes)
  - PaymentMatch (with status enum)

- [ ] **Create enums** `priority:1` `phase:data`
  - MatchStatus (pending/confirmed/rejected)
  - MatchConfidence (high/medium/low)

### Phase 2: Services

- [ ] **Implement TransactionImportService** `priority:1` `phase:service`
  - Normalize transaction data
  - Generate unique transaction IDs
  - Parse Verwendungszweck fields

- [ ] **Implement PaymentMatchingService** `priority:1` `phase:service`
  - Invoice number matching (high confidence)
  - Amount + client IBAN matching (medium confidence)
  - Amount-only matching (low confidence)
  - Confirm/reject methods

- [ ] **Implement PaymentReminderService** `priority:2` `phase:service`
  - Check overdue threshold
  - Check reminder interval
  - Dispatch reminder emails

- [ ] **Implement FintsService** `priority:2` `phase:service` `deps:registration`
  - Integrate abiturma/laravel-fints
  - Handle connection errors gracefully
  - Support TAN if required

### Phase 3: Scheduled Jobs

- [ ] **Create SyncBankTransactions job** `priority:2` `phase:jobs`
  - Loop through active connections
  - Import new transactions
  - Trigger matching

- [ ] **Configure scheduler** `priority:2` `phase:jobs`
  - Bank sync at 7:00 and 17:00
  - Payment reminders at 8:00

### Phase 4: Filament UI

- [ ] **Create PendingPaymentsWidget** `priority:1` `phase:ui`
  - Table widget with confirm/reject actions
  - Conditional visibility
  - Filament notifications on action

- [ ] **Add bank settings to Settings page** `priority:2` `phase:ui`
  - Connection credentials section
  - Reminder configuration section
  - Test connection action

- [ ] **Create CSV import action** `priority:3` `phase:ui`
  - File upload modal
  - Parser for common CSV formats
  - CAMT.053 XML support

### Phase 5: Testing

- [ ] **Write feature tests** `priority:1` `phase:test`
  - PaymentMatchingService tests
  - High/medium/low confidence scenarios
  - Confirm/reject flows

- [ ] **Write unit tests** `priority:2` `phase:test`
  - Transaction ID generation
  - Invoice number parsing
  - Reminder eligibility logic

### Phase 6: Documentation & Deployment

- [ ] **Register with Deutsche Kreditwirtschaft** `priority:1` `phase:deploy`
  - Submit PDF form
  - Wait for Product ID (2-8 weeks)
  - Configure in .env

- [ ] **Update user documentation** `priority:3` `phase:docs`
  - Bank connection setup guide
  - Troubleshooting FAQ

---

## Settings Keys

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `fints_bank_code` | string | - | Bank BLZ |
| `fints_username` | encrypted | - | FinTS username |
| `fints_pin` | encrypted | - | FinTS PIN |
| `fints_url` | string | - | FinTS server URL |
| `fints_enabled` | boolean | false | Enable auto-sync |
| `reminder_days_overdue` | integer | 3 | Days after due date for first reminder |
| `reminder_interval_days` | integer | 7 | Days between reminders |

---

## Risks and Mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| Registration delay (8+ weeks) | Blocks FinTS feature | Implement CSV import first as fallback |
| Bank doesn't support FinTS well | Feature unusable | Test with specific bank before committing; CSV fallback |
| PSD2 TAN requirements | Complex auth flow | Start with TAN-free statement retrieval; research TAN handling |
| Incorrect matches | User frustration | Always require confirmation; show confidence level; good rejection flow |
| Credential security | Data breach risk | Encrypt at rest; never log credentials; per-user encryption keys |

---

## Future Enhancements

1. **Cash Flow Forecasting** - Use transaction history to predict future cash flow
2. **Tax Preparation Export** - Export matched payments for tax reporting
3. **Multi-Account Support** - Manage multiple bank accounts
4. **Partial Payment Matching** - Handle payments that don't match exact amounts
5. **Direct Debit (Lastschrift)** - Initiate collections via FinTS
6. **Real-time Notifications** - WebSocket updates when payments arrive

---

## References

- [FinTS Wikipedia](https://en.wikipedia.org/wiki/FinTS)
- [nemiah/phpFinTS](https://github.com/nemiah/phpFinTS) - PHP FinTS library
- [abiturma/laravel-fints](https://packagist.org/packages/abiturma/laravel-fints) - Laravel wrapper
- [Deutsche Kreditwirtschaft FinTS](https://die-dk.de/zahlungsverkehr/electronic-banking/fints/) - Registration
- [MT940 Format Overview](https://www.sepaforcorporates.com/swift-for-corporates/account-statement-mt940-file-format-overview/)
- [CAMT.053 Guide](https://www.sepaforcorporates.com/swift-for-corporates/a-practical-guide-to-the-bank-statement-camt-053-format/)
