# Feature: Reminder Webhooks

## Summary

Send HTTP POST webhooks to external systems (n8n, Zapier, Make) when reminders become due, enabling custom automation workflows. Fire-and-forget delivery with HMAC-SHA256 signing and status visibility in Settings.

## Requirements

### From User

| Category | Selection | Details |
|----------|-----------|---------|
| Goal | New Functionality | Implement webhook feature as specified in iteration-6 docs |
| Scope | Full Implementation | WebhookService, Settings UI, Job integration, tests |
| Timeline | Flexible | No hard deadline, quality over speed |
| Testing | TDD | Write tests first, then implementation |
| Retries | No Retries | Fire-and-forget as designed |
| Error Visibility | Settings Status | Show last webhook status/error in Settings page |
| URL Privacy | Plain Storage | URL stored unencrypted for easier debugging |
| Payload | As Designed | Use payload structure from iteration-6 spec |

### Functional Requirements

1. **Webhook Dispatch** - Send HTTP POST to user-configured URL when reminders come due
2. **Secure Signing** - HMAC-SHA256 signature for payload verification
3. **Simple Configuration** - Settings UI for URL, secret, enable/disable toggle
4. **Status Visibility** - Show last webhook result (success/error) in Settings page
5. **Test Button** - Allow user to verify configuration before relying on it

### Non-Functional Requirements

- 5-second timeout to prevent queue worker blocking
- Graceful error handling - webhook failures must not break notification flow
- Logging to Laravel logs for debugging

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
│  │  • Last status: "Success" / "Error: Connection refused"             │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
│  ┌─────────────────┐    ┌──────────────────┐    ┌─────────────────────┐   │
│  │ ReminderService │───►│ SendReminder-    │───►│ WebhookService      │   │
│  │ (scheduler)     │    │ Notification Job │    │ • Build payload     │   │
│  │                 │    │ (existing)       │    │ • Sign with HMAC    │   │
│  │ processDue-     │    │                  │    │ • POST to URL       │   │
│  │ Reminders()     │    │ + sendWebhook()  │    │ • Update status     │   │
│  └─────────────────┘    └──────────────────┘    └─────────────────────┘   │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Data Model

### Settings Keys (Using Existing SettingsService)

| Key | Type | Storage | Description |
|-----|------|---------|-------------|
| `webhook_enabled` | boolean | Plain | Master toggle for webhook dispatch |
| `webhook_url` | string | Plain | Full webhook URL |
| `webhook_secret` | string | Encrypted | HMAC signing secret |
| `webhook_last_status` | string | Plain | Last result: "success" or "error" |
| `webhook_last_error` | string | Plain | Error message if last attempt failed |
| `webhook_last_sent_at` | string | Plain | ISO timestamp of last webhook attempt |

No new database tables required.

## API Design

### Webhook Payload: `reminder.due`

```json
{
  "event": "reminder.due",
  "timestamp": "2026-02-03T10:00:00+01:00",
  "reminder": {
    "id": 42,
    "title": "Follow up with Acme Corp",
    "description": "Check if they reviewed the offer",
    "due_at": "2026-02-03T09:00:00+01:00",
    "priority": "high",
    "recurrence": "weekly",
    "is_system": false,
    "system_type": null
  },
  "related_entity": {
    "type": "client",
    "id": 15,
    "name": "Acme Corp",
    "url": "https://crm.example.com/admin/clients/15/edit"
  }
}
```

### Webhook Payload: `webhook.test`

```json
{
  "event": "webhook.test",
  "timestamp": "2026-02-03T10:00:00+01:00",
  "message": "This is a test webhook from FreelancerCRM"
}
```

### HTTP Headers

| Header | Value | Description |
|--------|-------|-------------|
| `Content-Type` | `application/json` | Payload format |
| `X-Webhook-Event` | `reminder.due` | Event type |
| `X-Webhook-Signature` | `sha256=<hex>` | HMAC-SHA256 signature |
| `User-Agent` | `FreelancerCRM/1.0` | Identifying agent |

### Signature Verification

```php
$signature = 'sha256=' . hash_hmac('sha256', $jsonPayload, $secret);
```

## Component Design

### WebhookService

**File:** `app/Services/WebhookService.php`

```php
class WebhookService
{
    private const TIMEOUT_SECONDS = 5;
    private const USER_AGENT = 'FreelancerCRM/1.0';

    public function __construct(private SettingsService $settings) {}

    // Check if webhooks are enabled and configured
    public function isEnabled(): bool;

    // Send webhook for a due reminder
    public function sendReminderDueWebhook(Reminder $reminder): bool;

    // Send a test webhook to verify configuration
    public function sendTestWebhook(): bool;

    // Get last webhook status for Settings display
    public function getLastStatus(): ?array;

    // Private helpers
    private function buildReminderPayload(Reminder $reminder): array;
    private function getRelatedEntityData(Reminder $reminder): ?array;
    private function getEntityName(mixed $entity, string $type): ?string;
    private function getEntityUrl(mixed $entity, string $type): ?string;
    private function send(string $event, array $payload): bool;
    private function sign(string $payload, ?string $secret): string;
    private function updateStatus(bool $success, ?string $error = null): void;
}
```

### Settings UI Section

**File:** `app/Filament/Pages/Settings.php` (add section)

```php
Section::make('Webhooks')
    ->description('Sende Benachrichtigungen an externe Systeme wie n8n oder Zapier.')
    ->icon('heroicon-o-globe-alt')
    ->schema([
        Toggle::make('webhook_enabled')
            ->label('Webhook aktivieren')
            ->helperText('Sendet eine HTTP-Anfrage wenn Erinnerungen fällig werden.')
            ->live(),

        TextInput::make('webhook_url')
            ->label('Webhook URL')
            ->url()
            ->placeholder('https://n8n.example.com/webhook/abc123')
            ->helperText('Die URL die aufgerufen wird (POST-Anfrage).')
            ->visible(fn (Get $get) => $get('webhook_enabled')),

        TextInput::make('webhook_secret')
            ->label('Webhook Secret')
            ->password()
            ->revealable()
            ->dehydrateStateUsing(fn ($state) => $state ? encrypt($state) : null)
            ->helperText('Geheimer Schlüssel zur Signatur-Verifizierung (HMAC-SHA256).')
            ->visible(fn (Get $get) => $get('webhook_enabled')),

        Placeholder::make('webhook_status')
            ->label('Letzter Status')
            ->content(fn () => $this->getWebhookStatusDisplay())
            ->visible(fn (Get $get) => $get('webhook_enabled')),

        Actions::make([
            Action::make('testWebhook')
                ->label('Webhook testen')
                ->icon('heroicon-o-paper-airplane')
                ->action(fn () => $this->testWebhook()),
        ])->visible(fn (Get $get) => $get('webhook_enabled') && $get('webhook_url')),
    ])
    ->collapsible(),
```

### Job Integration

**File:** `app/Jobs/SendReminderNotification.php` (add method)

```php
private function sendWebhook(): void
{
    try {
        $webhookService = app(WebhookService::class, [
            'settings' => new SettingsService($this->user),
        ]);

        if ($webhookService->isEnabled()) {
            $webhookService->sendReminderDueWebhook($this->reminder);
        }
    } catch (\Exception $e) {
        Log::error('Failed to send reminder webhook', [
            'reminder_id' => $this->reminder->id,
            'error' => $e->getMessage(),
        ]);
        // Don't re-throw - webhook failure shouldn't fail the job
    }
}
```

## Error Handling

| Scenario | Behavior |
|----------|----------|
| Webhook disabled | Skip silently, return false |
| URL empty | Skip silently, return false |
| HTTP 4xx/5xx response | Log warning, update status to error, return false |
| Connection timeout | Log error, update status to error, return false |
| Connection refused | Log error, update status to error, return false |
| Invalid secret (decryption fails) | Use raw value as fallback |
| Any exception in job | Log error, continue job execution |

## Testing Strategy

### Test Cases (Pest)

**File:** `tests/Feature/WebhookServiceTest.php`

1. **Disabled/Unconfigured States**
   - `it('does not send webhook when disabled')`
   - `it('does not send webhook when url is empty')`

2. **Payload Structure**
   - `it('sends webhook with correct payload structure')`
   - `it('includes related entity data when reminder has remindable')`
   - `it('handles reminder without remindable gracefully')`

3. **HTTP Integration**
   - `it('includes correct headers')`
   - `it('generates valid hmac signature')`
   - `it('sends to correct url')`

4. **Error Handling**
   - `it('handles http errors gracefully')`
   - `it('handles connection errors gracefully')`
   - `it('updates status on success')`
   - `it('updates status on failure')`

5. **Test Webhook**
   - `it('sends test webhook successfully')`
   - `it('test webhook has correct event type')`

## Implementation Tasks

- [ ] **Write WebhookService tests** `priority:1` `phase:test`
  - files: tests/Feature/WebhookServiceTest.php
  - [ ] Create test file with all test cases
  - [ ] Verify tests fail (TDD red phase)

- [ ] **Create WebhookService** `priority:2` `phase:service`
  - files: app/Services/WebhookService.php
  - [ ] Implement all methods
  - [ ] Run tests, verify they pass (TDD green phase)

- [ ] **Add Settings UI section** `priority:3` `phase:ui`
  - files: app/Filament/Pages/Settings.php
  - [ ] Add Webhooks section with all fields
  - [ ] Add status display placeholder
  - [ ] Add test webhook action button
  - [ ] Add helper methods for status display and testing

- [ ] **Integrate with SendReminderNotification** `priority:4` `phase:integration`
  - files: app/Jobs/SendReminderNotification.php
  - [ ] Add WebhookService import
  - [ ] Add sendWebhook() method
  - [ ] Call sendWebhook() in handle() after notifications

- [ ] **Final verification** `priority:5` `phase:verify`
  - [ ] Run `sail test --filter=WebhookService`
  - [ ] Run `sail pint`
  - [ ] Manual test with webhook.site or similar

## Out of Scope

- Multiple webhook URLs per user
- Event filtering (other events besides reminder.due)
- Database delivery tracking/history
- Retry logic
- Webhook management UI beyond Settings section
