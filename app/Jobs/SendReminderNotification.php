<?php

namespace App\Jobs;

use App\Mail\ReminderDueMail;
use App\Models\Reminder;
use App\Services\EmailConfigurationService;
use App\Services\SettingsService;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendReminderNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public Reminder $reminder
    ) {}

    public function handle(): void
    {
        $user = $this->reminder->user;

        // 1. Send Filament database notification (bell icon)
        $this->sendDatabaseNotification();

        // 2. Send email notification if SMTP is configured
        $this->sendEmailNotification();

        // Mark reminder as notified
        $this->reminder->update(['notified_at' => now()]);

        Log::info("Sent notification for reminder: {$this->reminder->title}");
    }

    private function sendDatabaseNotification(): void
    {
        $notification = Notification::make()
            ->title('Erinnerung fällig')
            ->body($this->reminder->title)
            ->icon('heroicon-o-bell-alert')
            ->iconColor($this->reminder->priority->getColor());

        // Add action to view the reminder if it has a linked entity
        if ($this->reminder->remindable) {
            $notification->actions([
                Action::make('view')
                    ->label('Anzeigen')
                    ->url($this->getRemindableUrl())
                    ->markAsRead(),
            ]);
        }

        $notification->sendToDatabase($this->reminder->user);
    }

    private function sendEmailNotification(): void
    {
        $user = $this->reminder->user;
        $settings = new SettingsService($user);
        $config = new EmailConfigurationService($settings);

        // Only send email if SMTP is configured
        if (! $config->isConfigured()) {
            Log::info("Skipping email notification - SMTP not configured for user {$user->id}");

            return;
        }

        try {
            $mailer = $config->createMailer();
            $mailer->to($user->email, $user->name)
                ->send(new ReminderDueMail($this->reminder));

            Log::info("Sent email notification for reminder: {$this->reminder->title}");
        } catch (\Exception $e) {
            // Log error but don't fail the job - database notification was already sent
            Log::warning("Failed to send email for reminder {$this->reminder->id}: {$e->getMessage()}");
        }
    }

    private function getRemindableUrl(): ?string
    {
        $remindable = $this->reminder->remindable;

        if (! $remindable) {
            return null;
        }

        return match ($this->reminder->remindable_type) {
            \App\Models\Client::class => \App\Filament\Resources\Clients\ClientResource::getUrl('edit', ['record' => $remindable]),
            \App\Models\Project::class => \App\Filament\Resources\Projects\ProjectResource::getUrl('edit', ['record' => $remindable]),
            \App\Models\Invoice::class => \App\Filament\Resources\Invoices\InvoiceResource::getUrl('edit', ['record' => $remindable]),
            \App\Models\RecurringTask::class => \App\Filament\Resources\RecurringTasks\RecurringTaskResource::getUrl('edit', ['record' => $remindable]),
            default => null,
        };
    }
}
