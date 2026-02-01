<?php

namespace App\Services;

use App\Enums\EmailLogStatus;
use App\Enums\EmailLogType;
use App\Jobs\SendInvoiceEmail;
use App\Jobs\SendOfferEmail;
use App\Jobs\SendPaymentReminderEmail;
use App\Models\EmailLog;
use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;

class EmailService
{
    public function __construct(
        protected EmailConfigurationService $config,
        protected SettingsService $settings
    ) {}

    /**
     * Send offer email with PDF attachment.
     */
    public function sendOffer(Project $project): EmailLog
    {
        $log = EmailLog::create([
            'user_id' => auth()->id(),
            'emailable_type' => Project::class,
            'emailable_id' => $project->id,
            'type' => EmailLogType::Offer,
            'recipient_email' => $project->client->email,
            'recipient_name' => $project->client->display_name,
            'subject' => $this->parseTemplate('email_template_offer_subject', $project),
            'body' => $this->parseTemplate('email_template_offer_body', $project),
            'has_attachment' => true,
            'attachment_filename' => "Angebot-{$project->reference}.pdf",
            'status' => EmailLogStatus::Queued,
        ]);

        SendOfferEmail::dispatch($project, $log);

        return $log;
    }

    /**
     * Send invoice email with PDF attachment.
     */
    public function sendInvoice(Invoice $invoice): EmailLog
    {
        $log = EmailLog::create([
            'user_id' => auth()->id(),
            'emailable_type' => Invoice::class,
            'emailable_id' => $invoice->id,
            'type' => EmailLogType::Invoice,
            'recipient_email' => $invoice->client->email,
            'recipient_name' => $invoice->client->display_name,
            'subject' => $this->parseTemplate('email_template_invoice_subject', $invoice),
            'body' => $this->parseTemplate('email_template_invoice_body', $invoice),
            'has_attachment' => true,
            'attachment_filename' => "Rechnung-{$invoice->number}.pdf",
            'status' => EmailLogStatus::Queued,
        ]);

        SendInvoiceEmail::dispatch($invoice, $log);

        return $log;
    }

    /**
     * Send payment reminder for overdue invoice.
     */
    public function sendPaymentReminder(Invoice $invoice): EmailLog
    {
        $log = EmailLog::create([
            'user_id' => auth()->id(),
            'emailable_type' => Invoice::class,
            'emailable_id' => $invoice->id,
            'type' => EmailLogType::PaymentReminder,
            'recipient_email' => $invoice->client->email,
            'recipient_name' => $invoice->client->display_name,
            'subject' => $this->parseTemplate('email_template_reminder_subject', $invoice),
            'body' => $this->parseTemplate('email_template_reminder_body', $invoice),
            'has_attachment' => false,
            'status' => EmailLogStatus::Queued,
        ]);

        SendPaymentReminderEmail::dispatch($invoice, $log);

        return $log;
    }

    /**
     * Parse template placeholders.
     */
    public function parseTemplate(string $templateKey, Model $entity): string
    {
        $template = $this->settings->get($templateKey, $this->getDefaultTemplate($templateKey));

        $placeholders = $this->getPlaceholders($entity);

        return str_replace(
            array_keys($placeholders),
            array_values($placeholders),
            $template
        );
    }

    /**
     * Get available placeholders for an entity.
     *
     * @return array<string, string>
     */
    protected function getPlaceholders(Model $entity): array
    {
        $businessName = $this->settings->get('business_name', '');

        $placeholders = [
            '{business_name}' => $businessName,
            '{client_name}' => $entity->client->display_name ?? '',
        ];

        if ($entity instanceof Project) {
            $placeholders['{offer_number}'] = $entity->reference ?? (string) $entity->id;
            $placeholders['{project_title}'] = $entity->title ?? '';
        }

        if ($entity instanceof Invoice) {
            $placeholders['{invoice_number}'] = $entity->number ?? '';
            $placeholders['{invoice_total}'] = $entity->formatted_total ?? '';
            $placeholders['{due_date}'] = $entity->due_at?->format('d.m.Y') ?? '';
        }

        return $placeholders;
    }

    /**
     * Get default template for a given key.
     */
    protected function getDefaultTemplate(string $templateKey): string
    {
        return match ($templateKey) {
            'email_template_offer_subject' => 'Angebot von {business_name}',
            'email_template_offer_body' => "Sehr geehrte/r {client_name},\n\nanbei erhalten Sie unser Angebot.\n\nMit freundlichen Grüßen\n{business_name}",
            'email_template_invoice_subject' => 'Rechnung {invoice_number} von {business_name}',
            'email_template_invoice_body' => "Sehr geehrte/r {client_name},\n\nanbei erhalten Sie die Rechnung {invoice_number}.\n\nBitte überweisen Sie den Betrag von {invoice_total} bis zum {due_date}.\n\nMit freundlichen Grüßen\n{business_name}",
            'email_template_reminder_subject' => 'Zahlungserinnerung: Rechnung {invoice_number}',
            'email_template_reminder_body' => "Sehr geehrte/r {client_name},\n\nwir möchten Sie freundlich an die ausstehende Zahlung für Rechnung {invoice_number} über {invoice_total} erinnern.\n\nDas Zahlungsziel war der {due_date}.\n\nBitte überweisen Sie den Betrag zeitnah.\n\nMit freundlichen Grüßen\n{business_name}",
            default => '',
        };
    }
}
