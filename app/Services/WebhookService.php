<?php

namespace App\Services;

use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\RecurringTasks\RecurringTaskResource;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\RecurringTask;
use App\Models\Reminder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    private const TIMEOUT_SECONDS = 5;

    private const USER_AGENT = 'FreelancerCRM/1.0';

    public function __construct(
        private SettingsService $settings
    ) {}

    public function isEnabled(): bool
    {
        $enabled = $this->settings->get('webhook_enabled', false);
        $url = $this->settings->get('webhook_url', '');

        return $enabled && ! empty($url);
    }

    public function sendReminderDueWebhook(Reminder $reminder): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $payload = $this->buildReminderPayload($reminder);

        return $this->send('reminder.due', $payload);
    }

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
     * @return array{status: string, error: string|null, sent_at: string|null}|null
     */
    public function getLastStatus(): ?array
    {
        $status = $this->settings->get('webhook_last_status');

        if (! $status) {
            return null;
        }

        return [
            'status' => $status,
            'error' => $this->settings->get('webhook_last_error'),
            'sent_at' => $this->settings->get('webhook_last_sent_at'),
        ];
    }

    /**
     * @return array<string, mixed>
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
     * @return array{type: string, id: int, name: string|null, url: string|null}|null
     */
    private function getRelatedEntityData(Reminder $reminder): ?array
    {
        if (! $reminder->remindable_type || ! $reminder->remindable_id) {
            return null;
        }

        $entity = $reminder->remindable;

        if (! $entity) {
            return null;
        }

        $type = match ($reminder->remindable_type) {
            Client::class => 'client',
            Project::class => 'project',
            Invoice::class => 'invoice',
            RecurringTask::class => 'recurring_task',
            default => 'unknown',
        };

        return [
            'type' => $type,
            'id' => $entity->id,
            'name' => $this->getEntityName($entity, $type),
            'url' => $this->getEntityUrl($entity, $type),
        ];
    }

    private function getEntityName(mixed $entity, string $type): ?string
    {
        return match ($type) {
            'client' => $entity->display_name,
            'project' => $entity->title,
            'invoice' => "Rechnung {$entity->number}",
            'recurring_task' => $entity->name,
            default => null,
        };
    }

    private function getEntityUrl(mixed $entity, string $type): ?string
    {
        return match ($type) {
            'client' => ClientResource::getUrl('edit', ['record' => $entity]),
            'project' => ProjectResource::getUrl('edit', ['record' => $entity]),
            'invoice' => InvoiceResource::getUrl('edit', ['record' => $entity]),
            'recurring_task' => RecurringTaskResource::getUrl('edit', ['record' => $entity]),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(string $event, array $payload): bool
    {
        $url = $this->settings->get('webhook_url');
        $secret = $this->getSecret();

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
                $this->updateStatus(true);
                Log::info("Webhook sent successfully to {$url}", ['event' => $event]);

                return true;
            }

            $error = "HTTP {$response->status()}: {$response->body()}";
            $this->updateStatus(false, $error);
            Log::warning("Webhook failed to {$url}", ['event' => $event, 'status' => $response->status()]);

            return false;
        } catch (ConnectionException $e) {
            $error = "Connection error: {$e->getMessage()}";
            $this->updateStatus(false, $error);
            Log::error("Webhook connection failed to {$url}", ['event' => $event, 'error' => $e->getMessage()]);

            return false;
        } catch (\Exception $e) {
            $error = "Error: {$e->getMessage()}";
            $this->updateStatus(false, $error);
            Log::error("Webhook error for {$url}", ['event' => $event, 'error' => $e->getMessage()]);

            return false;
        }
    }

    private function getSecret(): ?string
    {
        $secret = $this->settings->get('webhook_secret');

        if (! $secret) {
            return null;
        }

        try {
            return decrypt($secret);
        } catch (\Exception) {
            return $secret;
        }
    }

    private function sign(string $payload, ?string $secret): string
    {
        $key = $secret ?? '';

        return 'sha256='.hash_hmac('sha256', $payload, $key);
    }

    private function updateStatus(bool $success, ?string $error = null): void
    {
        $this->settings->set('webhook_last_status', $success ? 'success' : 'error');
        $this->settings->set('webhook_last_error', $error);
        $this->settings->set('webhook_last_sent_at', now()->toIso8601String());
    }
}
