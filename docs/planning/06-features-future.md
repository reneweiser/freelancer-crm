# Future Features Roadmap

Features planned for post-MVP iterations, organized by priority and complexity.

---

## Iteration 2: Communication & Automation

### Email Integration

**Goal:** Send offers and invoices directly from the CRM.

| Feature | Description |
|---------|-------------|
| SMTP configuration | Settings page for email config |
| Email templates | Customizable offer/invoice emails |
| Send offer action | Email offer PDF to client |
| Send invoice action | Email invoice PDF to client |
| Send reminder action | Payment reminder email |
| Email log | Track sent emails per entity |

**Technical Notes:**
- Use Laravel Mail with queued jobs
- Store email templates in settings
- Log sent emails with timestamp and recipient

### Reminder System

**Goal:** Never forget a follow-up or overdue invoice.

| Feature | Description |
|---------|-------------|
| Reminder model | Polymorphic reminders for any entity |
| ReminderResource | CRUD for reminders |
| Upcoming reminders widget | Dashboard widget |
| Automatic reminders | Trigger on overdue invoices |
| Email notifications | Send reminder emails |
| In-app notifications | Filament notifications |

**Trigger Events:**
- Invoice overdue (due_at + X days)
- Offer no response (sent + X days)
- Project inactive (no time entries in X days)
- Custom scheduled reminders

### Recurring Tasks

**Goal:** Track maintenance contracts and recurring work.

| Feature | Description |
|---------|-------------|
| RecurringTask model | With frequency and next_due |
| RecurringTaskResource | CRUD |
| Auto-scheduling | Generate reminders on schedule |
| Link to invoices | Auto-create invoice templates |

---

## Iteration 3: Enhanced Time Tracking

### Timer Functionality

**Goal:** Track time in real-time without leaving the app.

| Feature | Description |
|---------|-------------|
| Start/stop timer | Global timer button in navbar |
| Running timer display | Persistent display of active timer |
| Timer to entry | Convert running timer to time entry |
| Timer widget | Dashboard widget for quick access |

**Technical Notes:**
- Use Livewire for real-time updates
- Store timer state in session or database
- Handle browser refresh/close gracefully

### Time Reports

**Goal:** Understand where time is spent.

| Feature | Description |
|---------|-------------|
| Time by project | Total hours per project |
| Time by client | Total hours per client |
| Time by period | Weekly/monthly summaries |
| Billable vs non-billable | Comparison charts |
| Export time entries | CSV export for external billing |

---

## Iteration 4: Financial Insights

### Advanced Reporting

**Goal:** Comprehensive financial overview for business decisions.

| Feature | Description |
|---------|-------------|
| Revenue by client | Who brings the most revenue |
| Revenue by project type | Fixed vs hourly comparison |
| Revenue trends | Monthly/quarterly/yearly charts |
| Outstanding receivables | Aging report |
| Tax summary | Quarterly VAT summary |

### Tax Season Features

**Goal:** Make tax filing effortless.

| Feature | Description |
|---------|-------------|
| Annual summary | Yearly income/expense overview |
| VAT summary | Collected VAT by quarter |
| DATEV export | German accounting software format |
| PDF summary report | Printable year summary |
| Income by category | If expense tracking added |

---

## Iteration 5: Team Collaboration

### Multi-User Support

**Goal:** Allow team members to access the CRM.

| Feature | Description |
|---------|-------------|
| User roles | Owner, member, viewer |
| Permission system | Role-based access control |
| User invitations | Invite team members |
| Activity log | Track who did what |
| User assignment | Assign projects to team members |

**Roles:**
- **Owner**: Full access, settings, user management
- **Member**: Projects, time tracking, limited invoicing
- **Viewer**: Read-only access (for accountant)

### Activity & Audit Log

| Feature | Description |
|---------|-------------|
| Activity model | Log all changes |
| Activity timeline | Per-entity activity feed |
| Audit trail | Who changed what, when |
| Global activity log | Admin view of all activity |

---

## Iteration 6: Client Experience

### Client Portal

**Goal:** Clients can view their projects and invoices.

| Feature | Description |
|---------|-------------|
| Client authentication | Separate client login |
| Client panel | Separate Filament panel |
| Project status view | Client sees their projects |
| Invoice access | View and download invoices |
| Payment status | See what's paid/outstanding |
| Optional: Online payment | Stripe/PayPal integration |

**Technical Notes:**
- Separate Filament panel at `/client`
- Client users linked to client records
- Strict data scoping to their data only

---

## Iteration 7: Integrations

### Calendar Integration

| Feature | Description |
|---------|-------------|
| Google Calendar sync | Sync reminders to calendar |
| Outlook Calendar sync | Alternative calendar option |
| iCal export | Export reminders as .ics |

### Accounting Software

| Feature | Description |
|---------|-------------|
| DATEV export | German standard |
| lexoffice integration | Popular German accounting |
| sevDesk integration | Another German option |
| Custom CSV formats | Configurable export templates |

### Banking

| Feature | Description |
|---------|-------------|
| Bank statement import | Match payments to invoices |
| Auto-reconciliation | Suggest payment matches |
| Payment tracking | Automated paid status updates |

---

## Iteration 8: Advanced Features

### Contracts & Documents

| Feature | Description |
|---------|-------------|
| Document storage | Attach files to clients/projects |
| Contract templates | Reusable contract documents |
| E-signature | Optional DocuSign/HelloSign integration |
| Document versioning | Track document changes |

### Expense Tracking

| Feature | Description |
|---------|-------------|
| Expense model | Track business expenses |
| Expense categories | Categorize for tax purposes |
| Receipt upload | Attach receipt images |
| Expense reports | Export for tax filing |
| Profit & loss | Revenue minus expenses |

### Project Templates

| Feature | Description |
|---------|-------------|
| Template model | Save projects as templates |
| Quick create | Create project from template |
| Template items | Pre-defined line items |
| Default settings | Standard terms, rates, etc. |

---

## Technical Debt & Improvements

### Performance

| Improvement | Description |
|-------------|-------------|
| Query optimization | Add missing indexes, eager loading |
| Caching | Cache dashboard stats, user settings |
| Pagination | Ensure all large lists paginate |
| Lazy loading | Implement for relation managers |

### Security

| Improvement | Description |
|-------------|-------------|
| Two-factor auth | Add 2FA option |
| Session management | View/revoke active sessions |
| IP logging | Track login locations |
| Encryption | Encrypt sensitive data at rest |

### Developer Experience

| Improvement | Description |
|-------------|-------------|
| Test coverage | Increase to >80% |
| API documentation | OpenAPI/Swagger docs |
| Admin CLI commands | Useful artisan commands |
| Seeder data | Realistic test data |

---

## Prioritization Matrix

| Feature | Impact | Effort | Priority |
|---------|--------|--------|----------|
| Email sending | High | Medium | P1 |
| Reminders | High | Medium | P1 |
| Timer | Medium | Low | P2 |
| Time reports | Medium | Medium | P2 |
| Recurring tasks | Medium | Low | P2 |
| Client portal | High | High | P3 |
| Multi-user | Medium | High | P3 |
| DATEV export | Medium | Medium | P3 |
| Expense tracking | Medium | High | P4 |
| Calendar sync | Low | Medium | P4 |
| Bank integration | High | Very High | P5 |

---

## Notes

- Each iteration should be a deployable release
- Gather user feedback before next iteration
- Re-prioritize based on actual usage
- Keep MVP lean, add complexity gradually
