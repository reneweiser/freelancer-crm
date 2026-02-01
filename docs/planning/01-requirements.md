# Requirements Specification

## User Roles

### Primary User: Freelancer/Owner
- Full access to all features
- Manages clients, projects, invoices
- Configures system settings
- Exports tax data

### Secondary User: Team Member
- Access to assigned projects
- Can log time entries
- Limited client data visibility
- Cannot access financial summaries

### Future: Accountant (Read-Only)
- View financial reports
- Export tax-relevant data
- No editing capabilities

---

## Functional Requirements

### FR-1: Client Management

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-1.1 | Store client contact information (name, email, phone, address) | Must |
| FR-1.2 | Store company details (company name, VAT ID) | Must |
| FR-1.3 | Add notes to clients | Must |
| FR-1.4 | View client project history | Must |
| FR-1.5 | View client invoice history | Must |
| FR-1.6 | Search and filter clients | Must |

### FR-2: Project Workflow

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-2.1 | Create offers/quotes with line items | Must |
| FR-2.2 | Convert accepted offers to active projects | Must |
| FR-2.3 | Track project status (draft, sent, accepted, declined, active, completed, cancelled) | Must |
| FR-2.4 | Support both hourly and fixed-price projects | Must |
| FR-2.5 | Add notes to projects | Must |
| FR-2.6 | Generate PDF offers | Must |
| FR-2.7 | Send offers via email | Should |

### FR-3: Time Tracking

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-3.1 | Log time entries with description | Must |
| FR-3.2 | Associate time entries with projects | Must |
| FR-3.3 | Calculate total hours per project | Must |
| FR-3.4 | Support different hourly rates per project | Must |
| FR-3.5 | Timer functionality for real-time tracking | Could |

### FR-4: Invoicing

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-4.1 | Create invoices with line items | Must |
| FR-4.2 | Auto-generate invoice numbers (sequential) | Must |
| FR-4.3 | Support German invoice requirements (Rechnungspflichtangaben) | Must |
| FR-4.4 | Calculate VAT (Mehrwertsteuer) correctly | Must |
| FR-4.5 | Track invoice status (draft, sent, paid, overdue, cancelled) | Must |
| FR-4.6 | Generate PDF invoices | Must |
| FR-4.7 | Send invoices via email | Should |
| FR-4.8 | Record payment date and method | Must |
| FR-4.9 | Support partial payments | Could |
| FR-4.10 | Create invoice from project (import time entries/line items) | Must |

### FR-5: Check-ups & Reminders

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-5.1 | Schedule follow-up reminders for clients | Must |
| FR-5.2 | Post-project review reminders (e.g., 30 days after completion) | Must |
| FR-5.3 | Recurring task management (maintenance contracts) | Must |
| FR-5.4 | Overdue invoice reminders | Must |
| FR-5.5 | Dashboard widget for upcoming tasks | Must |

### FR-6: Tax & Export

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-6.1 | Export invoices for a date range (CSV/Excel) | Must |
| FR-6.2 | Summarize income by month/quarter/year | Must |
| FR-6.3 | Summarize VAT collected | Must |
| FR-6.4 | Export client list | Should |
| FR-6.5 | Annual summary report | Must |

### FR-7: Dashboard

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-7.1 | Overview of unpaid invoices | Must |
| FR-7.2 | Upcoming reminders/tasks | Must |
| FR-7.3 | Recent activity feed | Should |
| FR-7.4 | Revenue summary (month/year) | Must |
| FR-7.5 | Active projects overview | Must |

---

## Non-Functional Requirements

### NFR-1: Security

| ID | Requirement |
|----|-------------|
| NFR-1.1 | All routes require authentication |
| NFR-1.2 | Passwords hashed with bcrypt/argon2 |
| NFR-1.3 | CSRF protection on all forms |
| NFR-1.4 | XSS prevention in all outputs |
| NFR-1.5 | Role-based access control |
| NFR-1.6 | Audit log for sensitive actions (future) |

### NFR-2: Performance

| ID | Requirement |
|----|-------------|
| NFR-2.1 | Page load < 2 seconds |
| NFR-2.2 | Efficient queries (no N+1) |
| NFR-2.3 | Pagination for large datasets |

### NFR-3: Maintainability

| ID | Requirement |
|----|-------------|
| NFR-3.1 | Follow Laravel conventions |
| NFR-3.2 | Use Filament 4 patterns consistently |
| NFR-3.3 | Type hints on all methods |
| NFR-3.4 | Service layer for business logic |

### NFR-4: Localization

| ID | Requirement |
|----|-------------|
| NFR-4.1 | German language interface |
| NFR-4.2 | Euro currency formatting |
| NFR-4.3 | German date format (DD.MM.YYYY) |
| NFR-4.4 | German number format (1.234,56) |

---

## User Stories

### Client Management
- As a freelancer, I want to store client contact information so I can reach them easily
- As a freelancer, I want to see all projects for a client so I understand our history
- As a freelancer, I want to add notes to clients so I remember important details

### Project Workflow
- As a freelancer, I want to create an offer with line items so I can send professional quotes
- As a freelancer, I want to convert an accepted offer to a project so I don't re-enter data
- As a freelancer, I want to track project status so I know what's active

### Invoicing
- As a freelancer, I want to generate invoices from projects so billing is fast
- As a freelancer, I want to track payment status so I know who owes me money
- As a freelancer, I want to send invoices via email so clients receive them immediately

### Tax Season
- As a freelancer, I want to export all invoices for a year so my accountant has the data
- As a freelancer, I want to see my VAT collected so I can file my Umsatzsteuervoranmeldung
- As a freelancer, I want a summary of my income so I can estimate my taxes

### Follow-ups
- As a freelancer, I want to schedule reminders so I don't forget to follow up
- As a freelancer, I want to see upcoming tasks so I plan my week
- As a freelancer, I want automatic overdue invoice alerts so I chase late payments
