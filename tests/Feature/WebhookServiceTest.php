<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Reminder;
use App\Models\Setting;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\WebhookService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('WebhookService - disabled/unconfigured states', function () {
    it('does not send webhook when disabled', function () {
        Http::fake();

        Setting::create(['user_id' => $this->user->id, 'key' => 'webhook_enabled', 'value' => false]);
        Setting::create(['user_id' => $this->user->id, 'key' => 'webhook_url', 'value' => 'https://example.com/webhook']);

        $settings = new SettingsService($this->user);
        $webhookService = new WebhookService($settings);

        $reminder = Reminder::factory()->create(['user_id' => $this->user->id]);

        $result = $webhookService->sendReminderDueWebhook($reminder);

        expect($result)->toBeFalse();
        Http::assertNothingSent();
    });

    it('does not send webhook when url is empty', function () {
        Http::fake();

        Setting::create(['user_id' => $this->user->id, 'key' => 'webhook_enabled', 'value' => true]);
        Setting::create(['user_id' => $this->user->id, 'key' => 'webhook_url', 'value' => '']);

        $settings = new SettingsService($this->user);
        $webhookService = new WebhookService($settings);

        $reminder = Reminder::factory()->create(['user_id' => $this->user->id]);

        $result = $webhookService->sendReminderDueWebhook($reminder);

        expect($result)->toBeFalse();
        Http::assertNothingSent();
    });

    it('returns false for isEnabled when disabled', function () {
        Setting::create(['user_id' => $this->user->id, 'key' => 'webhook_enabled', 'value' => false]);

        $settings = new SettingsService($this->user);
        $webhookService = new WebhookService($settings);

        expect($webhookService->isEnabled())->toBeFalse();
    });

    it('returns true for isEnabled when enabled and url configured', function () {
        Setting::create(['user_id' => $this->user->id, 'key' => 'webhook_enabled', 'value' => true]);
        Setting::create(['user_id' => $this->user->id, 'key' => 'webhook_url', 'value' => 'https://example.com/webhook']);

        $settings = new SettingsService($this->user);
        $webhookService = new WebhookService($settings);

        expect($webhookService->isEnabled())->toBeTrue();
    });
});

describe('WebhookService - payload structure', function () {
    beforeEach(function () {
        Setting::create(['user_id' => $this->user->id, 'key' => 'webhook_enabled', 'value' => true]);
        Setting::create(['user_id' => $this->user->id, 'key' => 'webhook_url', 'value' => 'https://example.com/webhook']);
        Setting::create(['user_id' => $this->user->id, 'key' => 'webhook_secret', 'value' => 'test-secret']);

        Http::fake(['*' => Http::response(['status' => 'ok'], 200)]);
    });

    it('sends webhook with correct payload structure', function () {
        $reminder = Reminder::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Follow up with client',
            'description' => 'Check if they reviewed the offer',
            'due_at' => now()->subHour(),
            'priority' => 'high',
            'recurrence' => 'weekly',
            'is_system' => false,
            'system_type' => null,
        ]);

        $settings = new SettingsService($this->user);
        $webhookService = new WebhookService($settings);

        $result = $webhookService->sendReminderDueWebhook($reminder);

        expect($result)->toBeTrue();

        Http::assertSent(function ($request) use ($reminder) {
            $body = $request->data();

            return $body['event'] === 'reminder.due'
                && isset($body['timestamp'])
                && $body['reminder']['id'] === $reminder->id
                && $body['reminder']['title'] === 'Follow up with client'
                && $body['reminder']['description'] === 'Check if they reviewed the offer'
                && $body['reminder']['priority'] === 'high'
                && $body['reminder']['recurrence'] === 'weekly'
                && $body['reminder']['is_system'] === false
                && $body['reminder']['system_type'] === null;
        });
    });

    it('includes related entity data when reminder has remindable', function () {
        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_name' => 'Acme Corp',
            'type' => 'company',
        ]);

        $reminder = Reminder::factory()->create([
            'user_id' => $this->user->id,
            'remindable_type' => Client::class,
            'remindable_id' => $client->id,
            'title' => 'Follow up with Acme',
        ]);

        $settings = new SettingsService($this->user);
        $webhookService = new WebhookService($settings);

        $result = $webhookService->sendReminderDueWebhook($reminder);

        expect($result)->toBeTrue();

        Http::assertSent(function ($request) use ($client) {
            $body = $request->data();

            return isset($body['related_entity'])
                && $body['related_entity']['type'] === 'client'
                && $body['related_entity']['id'] === $client->id
                && str_contains($body['related_entity']['name'] ?? '', 'Acme Corp');
        });
    });

    it('handles reminder without remindable gracefully', function () {
        $reminder = Reminder::factory()->create([
            'user_id' => $this->user->id,
            'remindable_type' => null,
            'remindable_id' => null,
            'title' => 'Standalone reminder',
        ]);

        $settings = new SettingsService($this->user);
        $webhookService = new WebhookService($settings);

        $result = $webhookService->sendReminderDueWebhook($reminder);

        expect($result)->toBeTrue();

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['related_entity'] === null;
        });
    });

    it('includes project data when reminder is linked to project', function () {
        $client = Client::factory()->create(['user_id' => $this->user->id]);
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'title' => 'Website Redesign',
        ]);

        $reminder = Reminder::factory()->create([
            'user_id' => $this->user->id,
            'remindable_type' => Project::class,
            'remindable_id' => $project->id,
        ]);

        $settings = new SettingsService($this->user);
        $webhookService = new WebhookService($settings);

        $webhookService->sendReminderDueWebhook($reminder);

        Http::assertSent(function ($request) use ($project) {
            $body = $request->data();

            return $body['related_entity']['type'] === 'project'
                && $body['related_entity']['id'] === $project->id
                && $body['related_entity']['name'] === 'Website Redesign';
        });
    });

    it('includes invoice data when reminder is linked to invoice', function () {
        $client = Client::factory()->create(['user_id' => $this->user->id]);
        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'number' => '2026-001',
        ]);

        $reminder = Reminder::factory()->create([
            'user_id' => $this->user->id,
            'remindable_type' => Invoice::class,
            'remindable_id' => $invoice->id,
        ]);

        $settings = new SettingsService($this->user);
        $webhookService = new WebhookService($settings);

        $webhookService->sendReminderDueWebhook($reminder);

        Http::assertSent(function ($request) use ($invoice) {
            $body = $request->data();

            return $body['related_entity']['type'] === 'invoice'
                && $body['related_entity']['id'] === $invoice->id
                && str_contains($body['related_entity']['name'], '2026-001');
        });
    });
});

describe('WebhookService - HTTP integration', function () {
    beforeEach(function () {
        Setting::create(['user_id' => $this->user->id, 'key' => 'webhook_enabled', 'value' => true]);
        Setting::create(['user_id' => $this->user->id, 'key' => 'webhook_url', 'value' => 'https://example.com/webhook']);
        Setting::create(['user_id' => $this->user->id, 'key' => 'webhook_secret', 'value' => 'test-secret']);

        Http::fake(['*' => Http::response(['status' => 'ok'], 200)]);
    });

    it('includes correct headers', function () {
        $reminder = Reminder::factory()->create(['user_id' => $this->user->id]);

        $settings = new SettingsService($this->user);
        $webhookService = new WebhookService($settings);

        $webhookService->sendReminderDueWebhook($reminder);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Content-Type', 'application/json')
                && $request->hasHeader('X-Webhook-Event', 'reminder.due')
                && $request->hasHeader('User-Agent', 'FreelancerCRM/1.0')
                && str_starts_with($request->header('X-Webhook-Signature')[0], 'sha256=');
        });
    });

    it('generates valid hmac signature', function () {
        $reminder = Reminder::factory()->create(['user_id' => $this->user->id]);

        $settings = new SettingsService($this->user);
        $webhookService = new WebhookService($settings);

        $webhookService->sendReminderDueWebhook($reminder);

        Http::assertSent(function ($request) {
            $signature = $request->header('X-Webhook-Signature')[0];
            $body = $request->body();

            $expectedSignature = 'sha256='.hash_hmac('sha256', $body, 'test-secret');

            return $signature === $expectedSignature;
        });
    });

    it('sends to correct url', function () {
        $reminder = Reminder::factory()->create(['user_id' => $this->user->id]);

        $settings = new SettingsService($this->user);
        $webhookService = new WebhookService($settings);

        $webhookService->sendReminderDueWebhook($reminder);

        Http::assertSent(function ($request) {
            return (string) $request->url() === 'https://example.com/webhook';
        });
    });

    it('works without secret configured', function () {
        Setting::where('user_id', $this->user->id)->where('key', 'webhook_secret')->delete();

        $reminder = Reminder::factory()->create(['user_id' => $this->user->id]);

        $settings = new SettingsService($this->user);
        $webhookService = new WebhookService($settings);

        $result = $webhookService->sendReminderDueWebhook($reminder);

        expect($result)->toBeTrue();

        Http::assertSent(function ($request) {
            $signature = $request->header('X-Webhook-Signature')[0];

            return str_starts_with($signature, 'sha256=');
        });
    });
});

describe('WebhookService - error handling', function () {
    it('handles http errors gracefully', function () {
        Setting::create(['user_id' => $this->user->id, 'key' => 'webhook_enabled', 'value' => true]);
        Setting::create(['user_id' => $this->user->id, 'key' => 'webhook_url', 'value' => 'https://example.com/webhook']);

        Http::fake(['https://example.com/webhook' => Http::response(['error' => 'Server error'], 500)]);

        $reminder = Reminder::factory()->create(['user_id' => $this->user->id]);

        $settings = new SettingsService($this->user);
        $webhookService = new WebhookService($settings);

        $result = $webhookService->sendReminderDueWebhook($reminder);

        expect($result)->toBeFalse();
    });

    it('handles connection errors gracefully', function () {
        Setting::create(['user_id' => $this->user->id, 'key' => 'webhook_enabled', 'value' => true]);
        Setting::create(['user_id' => $this->user->id, 'key' => 'webhook_url', 'value' => 'https://example.com/webhook']);

        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        });

        $reminder = Reminder::factory()->create(['user_id' => $this->user->id]);

        $settings = new SettingsService($this->user);
        $webhookService = new WebhookService($settings);

        $result = $webhookService->sendReminderDueWebhook($reminder);

        expect($result)->toBeFalse();
    });

    it('updates status on success', function () {
        Setting::create(['user_id' => $this->user->id, 'key' => 'webhook_enabled', 'value' => true]);
        Setting::create(['user_id' => $this->user->id, 'key' => 'webhook_url', 'value' => 'https://example.com/webhook']);

        Http::fake(['https://example.com/webhook' => Http::response(['status' => 'ok'], 200)]);

        $reminder = Reminder::factory()->create(['user_id' => $this->user->id]);

        $settings = new SettingsService($this->user);
        $webhookService = new WebhookService($settings);

        $webhookService->sendReminderDueWebhook($reminder);

        $status = $webhookService->getLastStatus();

        expect($status)->not->toBeNull();
        expect($status['status'])->toBe('success');
        expect($status['error'])->toBeNull();
        expect($status['sent_at'])->not->toBeNull();
    });

    it('updates status on failure', function () {
        Setting::create(['user_id' => $this->user->id, 'key' => 'webhook_enabled', 'value' => true]);
        Setting::create(['user_id' => $this->user->id, 'key' => 'webhook_url', 'value' => 'https://example.com/webhook']);

        Http::fake(['https://example.com/webhook' => Http::response(['error' => 'Bad request'], 400)]);

        $reminder = Reminder::factory()->create(['user_id' => $this->user->id]);

        $settings = new SettingsService($this->user);
        $webhookService = new WebhookService($settings);

        $webhookService->sendReminderDueWebhook($reminder);

        $status = $webhookService->getLastStatus();

        expect($status)->not->toBeNull();
        expect($status['status'])->toBe('error');
        expect($status['error'])->not->toBeNull();
        expect($status['sent_at'])->not->toBeNull();
    });

    it('stores connection error message', function () {
        Setting::create(['user_id' => $this->user->id, 'key' => 'webhook_enabled', 'value' => true]);
        Setting::create(['user_id' => $this->user->id, 'key' => 'webhook_url', 'value' => 'https://example.com/webhook']);

        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        });

        $reminder = Reminder::factory()->create(['user_id' => $this->user->id]);

        $settings = new SettingsService($this->user);
        $webhookService = new WebhookService($settings);

        $webhookService->sendReminderDueWebhook($reminder);

        $status = $webhookService->getLastStatus();

        expect($status['error'])->toContain('Connection refused');
    });
});

describe('WebhookService - test webhook', function () {
    beforeEach(function () {
        Setting::create(['user_id' => $this->user->id, 'key' => 'webhook_enabled', 'value' => true]);
        Setting::create(['user_id' => $this->user->id, 'key' => 'webhook_url', 'value' => 'https://example.com/webhook']);
        Setting::create(['user_id' => $this->user->id, 'key' => 'webhook_secret', 'value' => 'test-secret']);

        Http::fake(['*' => Http::response(['status' => 'ok'], 200)]);
    });

    it('sends test webhook successfully', function () {
        $settings = new SettingsService($this->user);
        $webhookService = new WebhookService($settings);

        $result = $webhookService->sendTestWebhook();

        expect($result)->toBeTrue();
        Http::assertSentCount(1);
    });

    it('test webhook has correct event type', function () {
        $settings = new SettingsService($this->user);
        $webhookService = new WebhookService($settings);

        $webhookService->sendTestWebhook();

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['event'] === 'webhook.test'
                && isset($body['timestamp'])
                && $body['message'] === 'This is a test webhook from FreelancerCRM'
                && $request->hasHeader('X-Webhook-Event', 'webhook.test');
        });
    });

    it('test webhook uses same signing mechanism', function () {
        $settings = new SettingsService($this->user);
        $webhookService = new WebhookService($settings);

        $webhookService->sendTestWebhook();

        Http::assertSent(function ($request) {
            $signature = $request->header('X-Webhook-Signature')[0];
            $body = $request->body();

            $expectedSignature = 'sha256='.hash_hmac('sha256', $body, 'test-secret');

            return $signature === $expectedSignature;
        });
    });

    it('returns false when webhook is not enabled for test', function () {
        Setting::where('user_id', $this->user->id)->where('key', 'webhook_enabled')->update(['value' => false]);

        $settings = new SettingsService($this->user);
        $webhookService = new WebhookService($settings);

        $result = $webhookService->sendTestWebhook();

        expect($result)->toBeFalse();
        Http::assertNothingSent();
    });
});
