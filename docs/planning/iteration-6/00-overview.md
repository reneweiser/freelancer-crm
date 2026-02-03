# Iteration 6: Reminder Webhooks

## Overview

Enable users to configure a webhook URL where FreelancerCRM sends a JSON payload whenever a reminder becomes due. Primary use case: trigger n8n/Zapier/Make workflows for custom automation.

## Goals

1. **Webhook Dispatch** - Send HTTP POST to user-configured URL when reminders come due
2. **Secure Signing** - HMAC-SHA256 signature for payload verification
3. **Simple Configuration** - Settings UI for URL, secret, and enable/disable toggle
4. **Minimal Footprint** - Fire-and-forget with logging, no database tables

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                      Reminder Webhook Integration                            │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │  Settings (Filament UI)                                              │   │
│  │  • webhook_url: "https://n8n.example.com/webhook/abc123"            │   │
│  │  • webhook_secret: "my-signing-secret" (encrypted)                  │   │
│  │  • webhook_enabled: true/false toggle                               │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
│  ┌─────────────────┐    ┌──────────────────┐    ┌─────────────────────┐   │
│  │ ReminderService │───►│ SendReminder-    │───►│ WebhookService      │   │
│  │ (scheduler)     │    │ Notification Job │    │ • Build payload     │   │
│  │                 │    │ (existing)       │    │ • Sign with HMAC    │   │
│  │ processDue-     │    │                  │    │ • POST to URL       │   │
│  │ Reminders()     │    │ + sendWebhook()  │    │ • Log result        │   │
│  └─────────────────┘    └──────────────────┘    └─────────────────────┘   │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Primary Use Case

**UC1: n8n Workflow Trigger**

User configures n8n webhook URL in Settings → Reminder becomes due → CRM sends POST with reminder data → n8n workflow triggers (e.g., send Slack message, create calendar event, send SMS).

## Scope

### In Scope
- Single webhook URL per user (stored in settings)
- HMAC-SHA256 signing with user-provided secret
- Fire-and-forget delivery (no retries)
- Laravel log file logging for debugging
- Settings UI with test webhook button
- Payload includes reminder data and related entity info

### Out of Scope
- Multiple webhook URLs
- Event filtering (other events besides reminder.due)
- Database delivery tracking
- Retry logic
- Webhook management UI (beyond settings)

## Success Criteria

1. Webhook fires within seconds of reminder becoming due
2. Payload can be verified using HMAC signature in n8n
3. Failed deliveries logged without breaking notification flow
4. Test webhook button confirms configuration works

## Dependencies

**Existing:**
- `SendReminderNotification` job (extend with webhook call)
- `SettingsService` (store webhook configuration)
- Laravel HTTP client (send requests)
- Filament Settings page (add UI section)

**New:**
- `WebhookService` class

## Implementation Phases

| Phase | Description | Priority |
|-------|-------------|----------|
| 1. Service | Create WebhookService with payload/signing/send | High |
| 2. Integration | Add webhook dispatch to SendReminderNotification | High |
| 3. Settings UI | Add webhook configuration section | High |
| 4. Testing | Feature tests for WebhookService | High |
| 5. Documentation | Update planning docs | Medium |

## Technical Decisions

- **No new tables:** Use existing settings key-value store
- **Fire-and-forget:** Simplicity over reliability (user's choice)
- **5-second timeout:** Prevent queue worker blocking
- **HMAC-SHA256:** Industry standard, supported by n8n/Zapier/Make
- **Log channel:** Use default Laravel log for simplicity

## Documents

- [01-reminder-webhooks.md](./01-reminder-webhooks.md) - Full design specification
