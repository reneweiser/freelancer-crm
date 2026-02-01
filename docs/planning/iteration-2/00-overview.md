# Iteration 2: Communication & Automation

## Overview

Iteration 2 focuses on automating communication with clients and ensuring nothing falls through the cracks. This iteration transforms the CRM from a passive record-keeping system into an active assistant that helps manage client relationships.

## Goals

1. **Reminder System** - Polymorphic reminders attached to any entity with notifications (implement first - foundation for other features)
2. **Email Integration** - Send offers and invoices directly from the CRM
3. **Recurring Tasks** - Track maintenance contracts and recurring work

## Implementation Order

Features have dependencies and should be implemented in this order:

```
1. Reminder System ──────> 2. Email Integration ──────> 3. Recurring Tasks
   (foundation)              (uses reminders for          (depends on both)
                              failure alerts)
```

## Scope

### In Scope
- SMTP configuration via settings page
- Email templates for offers, invoices, and payment reminders
- Send actions on Project and Invoice resources
- Email log tracking (who received what, when)
- Reminder model with polymorphic relations to Client/Project/Invoice
- Reminder CRUD via Filament resource
- Dashboard widget for upcoming reminders
- Automatic reminder creation for overdue invoices
- Filament notifications for due reminders
- RecurringTask model for maintenance contracts
- RecurringTask CRUD via Filament resource
- Scheduler job to process due recurring tasks

### Out of Scope (Future Iterations)
- Email templates designer (WYSIWYG editor)
- Email open/click tracking
- Calendar integration for reminders
- Auto-generation of invoices from recurring tasks
- SMS/WhatsApp notifications

## Success Criteria

### Email Integration
- [ ] User can configure SMTP settings and send a test email
- [ ] User receives clear success/failure feedback after test email
- [ ] User can send offer PDF to client via email with one click
- [ ] User can send invoice PDF to client via email with one click
- [ ] User can send payment reminder for overdue invoices
- [ ] Sent emails are logged and viewable per entity
- [ ] User receives notification when email fails to send
- [ ] Failed emails can be retried manually

### Reminder System
- [ ] User can create reminders attached to clients, projects, or invoices
- [ ] User sees upcoming reminders on the dashboard
- [ ] Dashboard widget shows CTA when no reminders exist
- [ ] User receives Filament notification when a reminder is due
- [ ] System-generated reminders are visually distinct from manual ones
- [ ] Overdue invoices automatically create payment reminders

### Recurring Tasks
- [ ] User can create recurring tasks with various frequencies
- [ ] Scheduler advances recurring tasks and creates reminders on due dates
- [ ] Paused recurring tasks show "paused" indicator clearly
- [ ] Navigation shows badge count for due tasks

## Data Model Changes

See individual feature docs for detailed migrations:
- `01-email-integration.md` - `email_logs` table
- `02-reminders.md` - `reminders` table (already in data model, needs implementation)
- `03-recurring-tasks.md` - `recurring_tasks` table (already in data model, needs implementation)

## Dependencies

- Laravel Mail (included in framework)
- Queued jobs for sending emails (already configured via Sail)
- Laravel Task Scheduling for recurring task processing

## Technical Considerations

### Critical: Global User Scopes
All new models MUST include `user_id` and implement a global scope to ensure users only see their own data:

```php
protected static function booted(): void
{
    static::addGlobalScope('user', function (Builder $builder) {
        if (auth()->check()) {
            $builder->where('user_id', auth()->id());
        }
    });
}
```

### Critical: Enum Contracts
All enums must implement Filament's `HasLabel` and `HasColor` contracts for proper badge rendering:

```php
enum Example: string implements HasLabel, HasColor
{
    public function getLabel(): string { ... }
    public function getColor(): string|array|null { ... }
}
```

### Queued Emails
All emails should be sent via queued jobs to prevent blocking the UI. Use `ShouldQueue` interface on all Mailables. Consider using a dedicated mailer instance per job to avoid race conditions with dynamic config.

### Email Templates
Start with Blade-based templates stored in `resources/views/emails/`. Consider moving to database-stored templates in a future iteration for user customization.

### Reminder Processing
Use Laravel's task scheduler (`routes/console.php` or `app/Console/Commands/`) to:
1. Check for due reminders and send notifications
2. Process recurring tasks and advance their `next_due_at`
3. Auto-create reminders for newly overdue invoices

### Eager Loading
All service methods that process collections must use eager loading to prevent N+1 queries:

```php
RecurringTask::query()->with('client')->active()->get();
```

### Factories Required
All new models need factory definitions for testing. Add factory creation to each migration path.

## Estimated Effort

| Feature | Complexity | Estimated Files |
|---------|------------|-----------------|
| Email Integration | Medium | 15-20 |
| Reminder System | Medium | 12-15 |
| Recurring Tasks | Low-Medium | 8-12 |

## Rollout Plan

1. Implement and test each feature independently
2. Deploy to staging for user acceptance testing
3. Gather feedback before production deployment
4. Monitor email deliverability and queue processing

## Related Documents

- [01-email-integration.md](./01-email-integration.md) - Email system specification
- [02-reminders.md](./02-reminders.md) - Reminder system specification
- [03-recurring-tasks.md](./03-recurring-tasks.md) - Recurring tasks specification
