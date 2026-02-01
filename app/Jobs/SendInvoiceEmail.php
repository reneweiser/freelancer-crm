<?php

namespace App\Jobs;

use App\Enums\InvoiceStatus;
use App\Mail\InvoiceMail;
use App\Models\EmailLog;
use App\Models\Invoice;
use App\Services\EmailConfigurationService;
use App\Services\SettingsService;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendInvoiceEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public Invoice $invoice,
        public EmailLog $emailLog
    ) {}

    public function handle(): void
    {
        try {
            $user = $this->invoice->user;
            $settings = new SettingsService($user);
            $config = new EmailConfigurationService($settings);

            $mailer = $config->createMailer();

            $mailer->to($this->invoice->client->email, $this->invoice->client->display_name)
                ->send(new InvoiceMail(
                    invoice: $this->invoice,
                    emailSubject: $this->emailLog->subject,
                    bodyText: $this->emailLog->body
                ));

            $this->emailLog->markAsSent();

            // Update invoice status to 'sent' if still draft
            if ($this->invoice->status === InvoiceStatus::Draft) {
                $this->invoice->update(['status' => InvoiceStatus::Sent]);
            }

        } catch (\Exception $e) {
            $this->emailLog->markAsFailed($e->getMessage());

            Notification::make()
                ->title('E-Mail-Versand fehlgeschlagen')
                ->body("Rechnung {$this->invoice->number} an {$this->invoice->client->display_name} konnte nicht gesendet werden: {$e->getMessage()}")
                ->danger()
                ->sendToDatabase($this->invoice->user);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->emailLog->markAsFailed('Alle Versuche fehlgeschlagen: '.$exception->getMessage());
    }
}
