# MVP Feature Breakdown

## Development Environment

This project uses **Laravel Sail** for local development. All commands should use `sail` prefix:

```bash
sail artisan ...     # Instead of php artisan
sail composer ...    # Instead of composer
sail npm ...         # Instead of npm
sail test            # Run tests
sail pint            # Code formatting
```

---

## MVP Scope Definition

The MVP focuses on the core freelancer workflow: **Client → Offer → Project → Invoice → Get Paid**.

Everything else is "nice to have" for later iterations.

---

## Phase 1: Foundation

### 1.1 Laravel & Filament Setup

| Task | Description |
|------|-------------|
| Initialize Laravel 12 project with Sail | `curl -s "https://laravel.build/reneweiser-crm?php=84" \| bash` |
| Start development environment | `cd reneweiser-crm && sail up -d` |
| Install Filament 4.6 | `sail composer require filament/filament:"^4.6" -W` |
| Install Laravel Boost (AI docs) | `sail composer require laravel/boost --dev && sail artisan boost:install` (select Laravel and Filament) |
| Configure panel | German locale, EUR currency, timezone |
| Setup authentication | Standard Filament auth with email verification |
| Configure database | MySQL (via Sail) with proper charset |

> **Note:** All commands use Laravel Sail. Run `sail --help` for available commands.

### 1.2 User & Settings

| Task | Description |
|------|-------------|
| User migration | Add role field |
| Settings table | Key-value store for business info |
| Settings page | Custom Filament page for business details |

**Settings to store:**
- Business name
- Business address (street, postal code, city)
- Tax number (Steuernummer)
- VAT ID (USt-IdNr.)
- Bank details (IBAN, BIC, bank name)
- Default payment terms (days)
- Default VAT rate
- Invoice number format

---

## Phase 2: Core Entities

### 2.1 Clients

| Task | Description |
|------|-------------|
| Client model & migration | As specified in data model |
| ClientResource | Full CRUD with Filament |
| Client form | Contact info, address, notes |
| Client table | Searchable, sortable, filterable |

**Acceptance Criteria:**
- [ ] Can create clients (company or individual)
- [ ] Can edit and delete clients
- [ ] Can search clients by name/email
- [ ] Can view client list with project/revenue stats

### 2.2 Projects

| Task | Description |
|------|-------------|
| Project model & migration | With status enum |
| ProjectItem model & migration | Line items |
| ProjectResource | Full CRUD |
| Project form | Client select, type toggle, items repeater |
| Project table | Status badges, filters |
| Status transitions | Validate allowed transitions |

**Acceptance Criteria:**
- [ ] Can create projects with line items
- [ ] Can select fixed or hourly pricing
- [ ] Can transition project through workflow states
- [ ] Can view projects by status

### 2.3 Invoices

| Task | Description |
|------|-------------|
| Invoice model & migration | With status enum |
| InvoiceItem model & migration | Line items with VAT |
| InvoiceResource | Full CRUD |
| Invoice form | Auto-number, items, auto-calculate totals |
| Invoice table | Status badges, date filters |
| Invoice number generation | Year-based sequential (2026-001) |

**Acceptance Criteria:**
- [ ] Can create invoices with line items
- [ ] Auto-generates sequential invoice numbers
- [ ] Auto-calculates subtotal, VAT, total
- [ ] Can filter by status, date range, year

---

## Phase 3: Key Workflows

### 3.1 Offer → Project Flow

| Task | Description |
|------|-------------|
| "Send Offer" action | Mark as sent, record timestamp |
| "Accept Offer" action | Transition to accepted |
| "Decline Offer" action | Transition to declined |
| "Start Project" action | Transition to in_progress |

### 3.2 Project → Invoice Flow

| Task | Description |
|------|-------------|
| "Create Invoice" action | On project, pre-fill from project |
| InvoiceCreationService | Copy items, handle hourly projects |
| Link invoice to project | Reference tracking |

**Acceptance Criteria:**
- [ ] Can create invoice directly from project
- [ ] Invoice pre-filled with project items
- [ ] For hourly projects, time entries converted to line item

### 3.3 Invoice Workflow

| Task | Description |
|------|-------------|
| "Mark as Sent" action | Update status, record timestamp |
| "Mark as Paid" action | Record payment date/method |
| Overdue detection | Scheduled command to update status |

---

## Phase 4: Time Tracking (Hourly Projects)

### 4.1 Basic Time Tracking

| Task | Description |
|------|-------------|
| TimeEntry model & migration | As specified |
| TimeEntryResource | CRUD for time entries |
| Project relation manager | View/add time entries on project |
| Duration calculation | Start/end or manual minutes |

**Acceptance Criteria:**
- [ ] Can log time against hourly projects
- [ ] Can mark entries as billable/non-billable
- [ ] Can see total hours per project
- [ ] Time entries included when creating invoice

---

## Phase 5: PDF Generation

### 5.1 Invoice PDF

| Task | Description |
|------|-------------|
| Install DomPDF | `sail composer require barryvdh/laravel-dompdf` |
| Invoice PDF template | German-compliant layout |
| PDF generation service | Generate and store |
| Download action | Stream PDF to browser |

**German Invoice Requirements:**
- [ ] Complete sender address
- [ ] Complete recipient address
- [ ] Invoice number and date
- [ ] Tax number or VAT ID
- [ ] Service description with dates
- [ ] Net amounts, VAT rates, gross total
- [ ] Payment terms

### 5.2 Offer PDF

| Task | Description |
|------|-------------|
| Offer PDF template | Similar to invoice, different labels |
| Download action | On project resource |

---

## Phase 6: Dashboard

### 6.1 Stats Overview Widget

| Task | Description |
|------|-------------|
| Open invoices count | With total amount |
| Monthly revenue | Current month paid invoices |
| Yearly revenue | Current year paid invoices |
| Active projects count | In progress projects |

### 6.2 Quick Actions Widget

| Task | Description |
|------|-------------|
| Create new client button | Quick access |
| Create new project button | Quick access |
| Create new invoice button | Quick access |

---

## Phase 7: Export

### 7.1 Invoice Export

| Task | Description |
|------|-------------|
| InvoiceExporter class | Using Filament export action |
| Export columns | All tax-relevant fields |
| Date range filter | For tax year exports |
| CSV/Excel format | User choice |

**Acceptance Criteria:**
- [ ] Can export invoices for a date range
- [ ] Export includes all fields needed for tax
- [ ] Can download as CSV or Excel

---

## MVP Checklist Summary

### Must Have (MVP)
- [x] Project setup (Laravel + Filament 4)
- [ ] User authentication
- [ ] Business settings page
- [ ] Client CRUD
- [ ] Project CRUD with line items
- [ ] Project status workflow
- [ ] Invoice CRUD with line items
- [ ] Invoice number auto-generation
- [ ] Invoice totals calculation
- [ ] Create invoice from project
- [x] Time entry CRUD (for hourly projects)
- [x] Invoice PDF generation
- [ ] Invoice export (CSV/Excel)
- [ ] Dashboard with key stats

### Nice to Have (Post-MVP)
- [x] Offer PDF generation
- [ ] Email sending
- [ ] Reminders system
- [ ] Recurring tasks
- [ ] Timer functionality
- [ ] Client portal
- [ ] Multi-user/team
- [ ] Audit logging

---

## Suggested Implementation Order

```
Week 1: Foundation
├── Laravel Sail + Filament setup
├── Laravel Boost (AI documentation MCP)
├── User auth + settings
└── German locale configuration

Week 2: Core Entities
├── Clients (model, resource, form, table)
├── Projects (model, resource, form, table)
└── Project items

Week 3: Invoices
├── Invoices (model, resource, form, table)
├── Invoice items
├── Auto-number generation
└── Totals calculation

Week 4: Workflows
├── Project status transitions
├── Create invoice from project
├── Time entries for hourly projects
└── Invoice payment tracking

Week 5: Documents & Export
├── Invoice PDF template
├── PDF generation service
├── Invoice export
└── Dashboard widgets

Week 6: Polish & Testing
├── Edge cases and validation
├── Error handling
├── UI polish
└── Testing critical paths
```

---

## Definition of Done

An MVP feature is "done" when:

1. **Functional**: Feature works as specified
2. **Tested**: Critical paths have tests
3. **Secure**: No obvious security issues
4. **Validated**: Form validation in place
5. **Styled**: Consistent with Filament theme
6. **German**: UI labels in German
7. **Documented**: Complex logic has comments
