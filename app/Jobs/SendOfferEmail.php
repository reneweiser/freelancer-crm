<?php

namespace App\Jobs;

use App\Enums\ProjectStatus;
use App\Mail\OfferMail;
use App\Models\EmailLog;
use App\Models\Project;
use App\Services\EmailConfigurationService;
use App\Services\SettingsService;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendOfferEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public Project $project,
        public EmailLog $emailLog
    ) {}

    public function handle(): void
    {
        try {
            $user = $this->project->user;
            $settings = new SettingsService($user);
            $config = new EmailConfigurationService($settings);

            $mailer = $config->createMailer();

            $mailer->to($this->project->client->email, $this->project->client->display_name)
                ->send(new OfferMail(
                    project: $this->project,
                    emailSubject: $this->emailLog->subject,
                    bodyText: $this->emailLog->body
                ));

            $this->emailLog->markAsSent();

            // Update project status to 'sent' if still draft
            if ($this->project->status === ProjectStatus::Draft) {
                $this->project->sendOffer();
            }

        } catch (\Exception $e) {
            $this->emailLog->markAsFailed($e->getMessage());

            // Notify user of failure via Filament database notification
            Notification::make()
                ->title('E-Mail-Versand fehlgeschlagen')
                ->body("Angebot an {$this->project->client->display_name} konnte nicht gesendet werden: {$e->getMessage()}")
                ->danger()
                ->sendToDatabase($this->project->user);

            throw $e;
        }
    }

    /**
     * Handle job failure after all retries exhausted.
     */
    public function failed(\Throwable $exception): void
    {
        $this->emailLog->markAsFailed('Alle Versuche fehlgeschlagen: '.$exception->getMessage());
    }
}
