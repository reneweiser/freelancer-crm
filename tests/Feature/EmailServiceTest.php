<?php

use App\Enums\EmailLogStatus;
use App\Enums\EmailLogType;
use App\Jobs\SendInvoiceEmail;
use App\Jobs\SendOfferEmail;
use App\Jobs\SendPaymentReminderEmail;
use App\Models\Client;
use App\Models\EmailLog;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use App\Services\EmailConfigurationService;
use App\Services\EmailService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();

    $this->user = User::factory()->create();
    $this->client = Client::factory()->create([
        'user_id' => $this->user->id,
        'email' => 'client@example.com',
    ]);

    Setting::create(['user_id' => $this->user->id, 'key' => 'business_name', 'value' => 'Test Business']);
    Setting::create(['user_id' => $this->user->id, 'key' => 'email_host', 'value' => 'smtp.test.com']);
    Setting::create(['user_id' => $this->user->id, 'key' => 'email_from_address', 'value' => 'test@example.com']);

    $this->settings = new SettingsService($this->user);
    $this->emailConfig = new EmailConfigurationService($this->settings);
    $this->emailService = new EmailService($this->emailConfig, $this->settings);

    $this->actingAs($this->user);
});

describe('EmailService - sendOffer', function () {
    it('creates email log when sending offer', function () {
        $project = Project::factory()->draft()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'reference' => 'TEST-001',
        ]);

        $log = $this->emailService->sendOffer($project);

        expect($log)->toBeInstanceOf(EmailLog::class);
        expect($log->type)->toBe(EmailLogType::Offer);
        expect($log->status)->toBe(EmailLogStatus::Queued);
        expect($log->recipient_email)->toBe('client@example.com');
        expect($log->emailable_type)->toBe(Project::class);
        expect($log->emailable_id)->toBe($project->id);
        expect($log->has_attachment)->toBeTrue();
    });

    it('dispatches SendOfferEmail job', function () {
        $project = Project::factory()->draft()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $this->emailService->sendOffer($project);

        Queue::assertPushed(SendOfferEmail::class, function ($job) use ($project) {
            return $job->project->id === $project->id;
        });
    });
});

describe('EmailService - sendInvoice', function () {
    it('creates email log when sending invoice', function () {
        $invoice = Invoice::factory()->draft()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'number' => '2026-001',
        ]);

        $log = $this->emailService->sendInvoice($invoice);

        expect($log)->toBeInstanceOf(EmailLog::class);
        expect($log->type)->toBe(EmailLogType::Invoice);
        expect($log->status)->toBe(EmailLogStatus::Queued);
        expect($log->recipient_email)->toBe('client@example.com');
        expect($log->emailable_type)->toBe(Invoice::class);
        expect($log->emailable_id)->toBe($invoice->id);
        expect($log->has_attachment)->toBeTrue();
    });

    it('dispatches SendInvoiceEmail job', function () {
        $invoice = Invoice::factory()->draft()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $this->emailService->sendInvoice($invoice);

        Queue::assertPushed(SendInvoiceEmail::class, function ($job) use ($invoice) {
            return $job->invoice->id === $invoice->id;
        });
    });
});

describe('EmailService - sendPaymentReminder', function () {
    it('creates email log when sending payment reminder', function () {
        $invoice = Invoice::factory()->overdue()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'number' => '2026-001',
        ]);

        $log = $this->emailService->sendPaymentReminder($invoice);

        expect($log)->toBeInstanceOf(EmailLog::class);
        expect($log->type)->toBe(EmailLogType::PaymentReminder);
        expect($log->status)->toBe(EmailLogStatus::Queued);
        expect($log->has_attachment)->toBeFalse();
    });

    it('dispatches SendPaymentReminderEmail job', function () {
        $invoice = Invoice::factory()->overdue()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $this->emailService->sendPaymentReminder($invoice);

        Queue::assertPushed(SendPaymentReminderEmail::class, function ($job) use ($invoice) {
            return $job->invoice->id === $invoice->id;
        });
    });
});

describe('EmailService - template parsing', function () {
    it('parses placeholders in templates', function () {
        Setting::create([
            'user_id' => $this->user->id,
            'key' => 'email_template_offer_subject',
            'value' => 'Angebot von {business_name} für {client_name}',
        ]);

        $project = Project::factory()->draft()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $log = $this->emailService->sendOffer($project);

        expect($log->subject)->toContain('Test Business');
        expect($log->subject)->toContain($this->client->display_name);
    });

    it('uses default templates when not configured', function () {
        $project = Project::factory()->draft()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $log = $this->emailService->sendOffer($project);

        expect($log->subject)->toContain('Angebot');
        expect($log->body)->not->toBeEmpty();
    });

    it('parses invoice-specific placeholders', function () {
        Setting::create([
            'user_id' => $this->user->id,
            'key' => 'email_template_invoice_subject',
            'value' => 'Rechnung {invoice_number} - {invoice_total}',
        ]);

        $invoice = Invoice::factory()->draft()->withTotals(1000)->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'number' => '2026-TEST',
        ]);

        $log = $this->emailService->sendInvoice($invoice);

        expect($log->subject)->toContain('2026-TEST');
    });
});

describe('EmailConfigurationService', function () {
    it('detects configured email settings', function () {
        expect($this->emailConfig->isConfigured())->toBeTrue();
    });

    it('detects missing email settings', function () {
        Setting::where('user_id', $this->user->id)
            ->where('key', 'email_host')
            ->delete();

        $settings = new SettingsService($this->user);
        $config = new EmailConfigurationService($settings);

        expect($config->isConfigured())->toBeFalse();
    });
});

describe('EmailLog model', function () {
    it('has correct user scope', function () {
        $otherUser = User::factory()->create();
        $otherClient = Client::factory()->create(['user_id' => $otherUser->id]);

        $myProject = Project::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $otherProject = Project::factory()->create([
            'user_id' => $otherUser->id,
            'client_id' => $otherClient->id,
        ]);

        EmailLog::factory()->forProject($myProject)->create();
        EmailLog::factory()->forProject($otherProject)->create();

        $logs = EmailLog::all();

        expect($logs)->toHaveCount(1);
        expect($logs->first()->user_id)->toBe($this->user->id);
    });

    it('can mark email as sent', function () {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $log = EmailLog::factory()->forProject($project)->queued()->create();

        $log->markAsSent();

        expect($log->status)->toBe(EmailLogStatus::Sent);
        expect($log->sent_at)->not->toBeNull();
    });

    it('can mark email as failed', function () {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $log = EmailLog::factory()->forProject($project)->queued()->create();

        $log->markAsFailed('Connection timeout');

        expect($log->status)->toBe(EmailLogStatus::Failed);
        expect($log->error_message)->toBe('Connection timeout');
    });

    it('can reset for retry', function () {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $log = EmailLog::factory()->forProject($project)->failed('Previous error')->create();

        $log->resetForRetry();

        expect($log->status)->toBe(EmailLogStatus::Queued);
        expect($log->error_message)->toBeNull();
    });
});
