<?php

namespace App\Jobs;

use App\Mail\PaymentReminderMail;
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

class SendPaymentReminderEmail implements ShouldQueue
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
                ->send(new PaymentReminderMail(
                    invoice: $this->invoice,
                    emailSubject: $this->emailLog->subject,
                    bodyText: $this->emailLog->body
                ));

            $this->emailLog->markAsSent();

        } catch (\Exception $e) {
            $this->emailLog->markAsFailed($e->getMessage());

            Notification::make()
                ->title('E-Mail-Versand fehlgeschlagen')
                ->body("Zahlungserinnerung für Rechnung {$this->invoice->number} konnte nicht gesendet werden: {$e->getMessage()}")
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
