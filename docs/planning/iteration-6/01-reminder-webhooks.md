# Reminder Webhooks - Design Specification

## Overview

Send HTTP POST webhooks to external systems (n8n, Zapier, Make) when reminders become due.

## Settings Configuration

### New Settings Keys

| Key | Type | Storage | Description |
|-----|------|---------|-------------|
| `webhook_enabled` | boolean | Plain | Master toggle for webhook dispatch |
| `webhook_url` | string | Plain | Full webhook URL |
| `webhook_secret` | string | Encrypted | HMAC signing secret |

### Filament Settings UI

Add new section "Webhooks" to `app/Filament/Pages/Settings.php`:

```php
Section::make('Webhooks')
    ->description('Sende Benachrichtigungen an externe Systeme wie n8n oder Zapier.')
    ->icon('heroicon-o-globe-alt')
    ->schema([
        Toggle::make('webhook_enabled')
            ->label('Webhook aktivieren')
            ->helperText('Sendet eine HTTP-Anfrage wenn Erinnerungen fällig werden.'),

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
            ->helperText('Geheimer Schlüssel zur Signatur-Verifizierung (HMAC-SHA256).')
            ->visible(fn (Get $get) => $get('webhook_enabled')),
    ])
    ->collapsible(),
```

### Test Webhook Action

Add action button to test configuration:

```php
Action::make('testWebhook')
    ->label('Webhook testen')
    ->icon('heroicon-o-paper-airplane')
    ->action(function () {
        $service = app(WebhookService::class, [
            'settings' => new SettingsService(auth()->user())
        ]);

        if ($service->sendTestWebhook()) {
            Notification::make()
                ->success()
                ->title('Webhook gesendet')
                ->body('Test-Anfrage wurde erfolgreich gesendet.')
                ->send();
        } else {
            Notification::make()
                ->danger()
                ->title('Webhook fehlgeschlagen')
                ->body('Die Anfrage konnte nicht gesendet werden. Prüfe die Logs.')
                ->send();
        }
    })
    ->visible(fn (Get $get) => $get('webhook_enabled') && $get('webhook_url')),
```

## Webhook Payload

### Event: `reminder.due`

Sent when a reminder becomes due (same timing as email/database notifications).

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

### Test Event Payload

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

The signature is computed as:

```php
$signature = 'sha256=' . hash_hmac('sha256', $jsonPayload, $secret);
```

**Verification in n8n:**

```javascript
// In n8n Function node
const crypto = require('crypto');
const payload = JSON.stringify($input.first().json);
const secret = 'your-webhook-secret';
const signature = 'sha256=' + crypto.createHmac('sha256', secret).update(payload).digest('hex');
const receivedSignature = $input.first().headers['x-webhook-signature'];

if (signature !== receivedSignature) {
  throw new Error('Invalid webhook signature');
}
```

## WebhookService Implementation

### File: `app/Services/WebhookService.php`

```php
<?php

namespace App\Services;

use App\Models\Reminder;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\RecurringTasks\RecurringTaskResource;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    private const TIMEOUT_SECONDS = 5;
    private const USER_AGENT = 'FreelancerCRM/1.0';

    public function __construct(
        private SettingsService $settings
    ) {}

    /**
     * Check if webhooks are enabled and configured.
     */
    public function isEnabled(): bool
    {
        return $this->settings->get('webhook_enabled', false)
            && ! empty($this->settings->get('webhook_url'));
    }

    /**
     * Send webhook for a due reminder.
     */
    public function sendReminderDueWebhook(Reminder $reminder): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $payload = $this->buildReminderPayload($reminder);

        return $this->send('reminder.due', $payload);
    }

    /**
     * Send a test webhook to verify configuration.
     */
    public function sendTestWebhook(): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $payload = [
            'event' => 'webhook.test',
            'timestamp' => now()->toIso8601String(),
            'message' => 'This is a test webhook from FreelancerCRM',
        ];

        return $this->send('webhook.test', $payload);
    }

    /**
     * Build the reminder payload.
     */
    private function buildReminderPayload(Reminder $reminder): array
    {
        return [
            'event' => 'reminder.due',
            'timestamp' => now()->toIso8601String(),
            'reminder' => [
                'id' => $reminder->id,
                'title' => $reminder->title,
                'description' => $reminder->description,
                'due_at' => $reminder->due_at?->toIso8601String(),
                'priority' => $reminder->priority?->value,
                'recurrence' => $reminder->recurrence?->value,
                'is_system' => $reminder->is_system,
                'system_type' => $reminder->system_type,
            ],
            'related_entity' => $this->getRelatedEntityData($reminder),
        ];
    }

    /**
     * Get related entity information if available.
     */
    private function getRelatedEntityData(Reminder $reminder): ?array
    {
        if (! $reminder->remindable) {
            return null;
        }

        $entity = $reminder->remindable;
        $type = strtolower(class_basename($entity));

        return [
            'type' => $type,
            'id' => $entity->id,
            'name' => $this->getEntityName($entity, $type),
            'url' => $this->getEntityUrl($entity, $type),
        ];
    }

    /**
     * Get the display name for an entity.
     */
    private function getEntityName(mixed $entity, string $type): ?string
    {
        return match ($type) {
            'client' => $entity->name ?? $entity->company_name,
            'project' => $entity->title ?? $entity->name,
            'invoice' => $entity->invoice_number,
            'recurringtask' => $entity->title ?? $entity->name,
            default => null,
        };
    }

    /**
     * Get the Filament edit URL for an entity.
     */
    private function getEntityUrl(mixed $entity, string $type): ?string
    {
        return match ($type) {
            'client' => ClientResource::getUrl('edit', ['record' => $entity]),
            'project' => ProjectResource::getUrl('edit', ['record' => $entity]),
            'invoice' => InvoiceResource::getUrl('edit', ['record' => $entity]),
            'recurringtask' => RecurringTaskResource::getUrl('edit', ['record' => $entity]),
            default => null,
        };
    }

    /**
     * Send the webhook request.
     */
    private function send(string $event, array $payload): bool
    {
        $url = $this->settings->get('webhook_url');
        $secret = $this->settings->get('webhook_secret');
        $jsonPayload = json_encode($payload);
        $signature = $this->sign($jsonPayload, $secret);

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Event' => $event,
                    'X-Webhook-Signature' => $signature,
                    'User-Agent' => self::USER_AGENT,
                ])
                ->withBody($jsonPayload, 'application/json')
                ->post($url);

            if ($response->successful()) {
                Log::info('Webhook sent successfully', [
                    'event' => $event,
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return true;
            }

            Log::warning('Webhook received non-success response', [
                'event' => $event,
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Webhook delivery failed', [
                'event' => $event,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Generate HMAC-SHA256 signature for payload.
     */
    private function sign(string $payload, ?string $secret): string
    {
        if (empty($secret)) {
            return '';
        }

        // Decrypt the secret if it was stored encrypted
        try {
            $decryptedSecret = decrypt($secret);
        } catch (\Exception) {
            $decryptedSecret = $secret;
        }

        return 'sha256=' . hash_hmac('sha256', $payload, $decryptedSecret);
    }
}
```

## SendReminderNotification Integration

### File: `app/Jobs/SendReminderNotification.php`

Add to existing `handle()` method after email notification:

```php
// Send webhook notification (if configured)
$this->sendWebhook();
```

Add new private method:

```php
/**
 * Send webhook notification if configured.
 */
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

## Test Cases

### File: `tests/Feature/WebhookServiceTest.php`

```php
<?php

use App\Models\Reminder;
use App\Models\User;
use App\Models\Client;
use App\Services\WebhookService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->settings = new SettingsService($this->user);
});

it('does not send webhook when disabled', function () {
    Http::fake();

    $this->settings->set('webhook_enabled', false);
    $this->settings->set('webhook_url', 'https://example.com/webhook');

    $service = new WebhookService($this->settings);
    $reminder = Reminder::factory()->for($this->user)->create();

    $result = $service->sendReminderDueWebhook($reminder);

    expect($result)->toBeFalse();
    Http::assertNothingSent();
});

it('does not send webhook when url is empty', function () {
    Http::fake();

    $this->settings->set('webhook_enabled', true);
    $this->settings->set('webhook_url', '');

    $service = new WebhookService($this->settings);
    $reminder = Reminder::factory()->for($this->user)->create();

    $result = $service->sendReminderDueWebhook($reminder);

    expect($result)->toBeFalse();
    Http::assertNothingSent();
});

it('sends webhook with correct payload structure', function () {
    Http::fake([
        '*' => Http::response([], 200),
    ]);

    $this->settings->set('webhook_enabled', true);
    $this->settings->set('webhook_url', 'https://example.com/webhook');
    $this->settings->set('webhook_secret', encrypt('test-secret'));

    $service = new WebhookService($this->settings);
    $reminder = Reminder::factory()->for($this->user)->create([
        'title' => 'Test Reminder',
        'description' => 'Test description',
    ]);

    $result = $service->sendReminderDueWebhook($reminder);

    expect($result)->toBeTrue();

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return $request->url() === 'https://example.com/webhook'
            && $body['event'] === 'reminder.due'
            && isset($body['timestamp'])
            && isset($body['reminder']['id'])
            && $body['reminder']['title'] === 'Test Reminder';
    });
});

it('includes correct headers', function () {
    Http::fake([
        '*' => Http::response([], 200),
    ]);

    $this->settings->set('webhook_enabled', true);
    $this->settings->set('webhook_url', 'https://example.com/webhook');
    $this->settings->set('webhook_secret', encrypt('test-secret'));

    $service = new WebhookService($this->settings);
    $reminder = Reminder::factory()->for($this->user)->create();

    $service->sendReminderDueWebhook($reminder);

    Http::assertSent(function ($request) {
        return $request->hasHeader('Content-Type', 'application/json')
            && $request->hasHeader('X-Webhook-Event', 'reminder.due')
            && $request->hasHeader('User-Agent', 'FreelancerCRM/1.0')
            && str_starts_with($request->header('X-Webhook-Signature')[0], 'sha256=');
    });
});

it('generates valid hmac signature', function () {
    Http::fake([
        '*' => Http::response([], 200),
    ]);

    $secret = 'my-test-secret';
    $this->settings->set('webhook_enabled', true);
    $this->settings->set('webhook_url', 'https://example.com/webhook');
    $this->settings->set('webhook_secret', encrypt($secret));

    $service = new WebhookService($this->settings);
    $reminder = Reminder::factory()->for($this->user)->create();

    $service->sendReminderDueWebhook($reminder);

    Http::assertSent(function ($request) use ($secret) {
        $body = $request->body();
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $body, $secret);

        return $request->header('X-Webhook-Signature')[0] === $expectedSignature;
    });
});

it('includes related entity data when reminder has remindable', function () {
    Http::fake([
        '*' => Http::response([], 200),
    ]);

    $this->settings->set('webhook_enabled', true);
    $this->settings->set('webhook_url', 'https://example.com/webhook');

    $client = Client::factory()->for($this->user)->create(['name' => 'Acme Corp']);
    $reminder = Reminder::factory()->for($this->user)->create([
        'remindable_type' => Client::class,
        'remindable_id' => $client->id,
    ]);

    $service = new WebhookService($this->settings);
    $service->sendReminderDueWebhook($reminder);

    Http::assertSent(function ($request) use ($client) {
        $body = json_decode($request->body(), true);

        return $body['related_entity']['type'] === 'client'
            && $body['related_entity']['id'] === $client->id
            && $body['related_entity']['name'] === 'Acme Corp';
    });
});

it('handles http errors gracefully', function () {
    Http::fake([
        '*' => Http::response([], 500),
    ]);

    $this->settings->set('webhook_enabled', true);
    $this->settings->set('webhook_url', 'https://example.com/webhook');

    $service = new WebhookService($this->settings);
    $reminder = Reminder::factory()->for($this->user)->create();

    $result = $service->sendReminderDueWebhook($reminder);

    expect($result)->toBeFalse();
});

it('handles connection errors gracefully', function () {
    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
    });

    $this->settings->set('webhook_enabled', true);
    $this->settings->set('webhook_url', 'https://example.com/webhook');

    $service = new WebhookService($this->settings);
    $reminder = Reminder::factory()->for($this->user)->create();

    $result = $service->sendReminderDueWebhook($reminder);

    expect($result)->toBeFalse();
});

it('sends test webhook successfully', function () {
    Http::fake([
        '*' => Http::response([], 200),
    ]);

    $this->settings->set('webhook_enabled', true);
    $this->settings->set('webhook_url', 'https://example.com/webhook');

    $service = new WebhookService($this->settings);
    $result = $service->sendTestWebhook();

    expect($result)->toBeTrue();

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return $body['event'] === 'webhook.test'
            && isset($body['message']);
    });
});
```

## Implementation Checklist

- [ ] **Create WebhookService** `priority:1`
  - [ ] Create `app/Services/WebhookService.php`
  - [ ] Implement `isEnabled()`, `sendReminderDueWebhook()`, `sendTestWebhook()`
  - [ ] Implement payload building with related entity data
  - [ ] Implement HMAC-SHA256 signing
  - [ ] Add logging for success/failure

- [ ] **Integrate with SendReminderNotification** `priority:2`
  - [ ] Add `use App\Services\WebhookService` import
  - [ ] Add `sendWebhook()` private method
  - [ ] Call `sendWebhook()` in `handle()` after email

- [ ] **Add Settings UI** `priority:3`
  - [ ] Add "Webhooks" section to Settings page
  - [ ] Add `webhook_enabled` toggle
  - [ ] Add `webhook_url` text input with URL validation
  - [ ] Add `webhook_secret` password input (encrypted)
  - [ ] Add "Test Webhook" action button

- [ ] **Write Feature Tests** `priority:4`
  - [ ] Create `tests/Feature/WebhookServiceTest.php`
  - [ ] Test disabled/unconfigured states
  - [ ] Test payload structure
  - [ ] Test signature generation
  - [ ] Test related entity inclusion
  - [ ] Test error handling

- [ ] **Run Tests & Lint** `priority:5`
  - [ ] `sail test --filter=WebhookService`
  - [ ] `sail pint`
