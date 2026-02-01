# Email Integration

## Overview

Enable sending offers, invoices, and payment reminders directly from the CRM via email. All sent emails are logged for audit and reference.

## Features

| Feature | Description | Priority |
|---------|-------------|----------|
| SMTP Configuration | Settings page fields for email config | P0 |
| Test Email | Send test email to verify configuration | P0 |
| Send Offer Email | Email offer PDF to client | P0 |
| Send Invoice Email | Email invoice PDF to client | P0 |
| Send Payment Reminder | Email payment reminder for overdue invoices | P1 |
| Email Log | Track sent emails per entity | P1 |
| Email Templates | Customizable email body text | P2 |

---

## Data Model

### email_logs

Tracks all emails sent from the system.

```php
Schema::create('email_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();

    // Polymorphic relation to the entity (Project for offers, Invoice for invoices)
    $table->nullableMorphs('emailable');

    // Email details
    $table->string('type'); // 'offer', 'invoice', 'payment_reminder', 'custom'
    $table->string('recipient_email');
    $table->string('recipient_name')->nullable();
    $table->string('subject');
    $table->text('body')->nullable(); // Stored for reference
    $table->boolean('has_attachment')->default(false);
    $table->string('attachment_filename')->nullable();

    // Status tracking
    $table->enum('status', ['queued', 'sent', 'failed'])->default('queued');
    $table->timestamp('sent_at')->nullable();
    $table->text('error_message')->nullable();

    $table->timestamps();

    $table->index(['user_id', 'status', 'created_at']);
    $table->index(['emailable_type', 'emailable_id']);
});
```

### EmailLog Model

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EmailLog extends Model
{
    protected $fillable = [
        'user_id',
        'emailable_type',
        'emailable_id',
        'type',
        'recipient_email',
        'recipient_name',
        'subject',
        'body',
        'has_attachment',
        'attachment_filename',
        'status',
        'sent_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'has_attachment' => 'boolean',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * Global scope to ensure users only see their own email logs.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('user', function (Builder $builder) {
            if (auth()->check()) {
                $builder->where('user_id', auth()->id());
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function emailable(): MorphTo
    {
        return $this->morphTo();
    }

    // Scopes

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', 'sent');
    }

    public function scopeQueued(Builder $query): Builder
    {
        return $query->where('status', 'queued');
    }
}
```

### Settings Keys (existing settings table)

Add these keys to the settings system:

```php
// SMTP Configuration
'email_host'        => 'smtp.example.com',
'email_port'        => '587',
'email_username'    => 'user@example.com',
'email_password'    => 'encrypted_password', // Encrypt at rest
'email_encryption'  => 'tls', // tls, ssl, null
'email_from_name'   => 'Max Mustermann',
'email_from_address'=> 'invoices@example.com',

// Email Templates (simple text with placeholders)
'email_template_offer_subject'    => 'Angebot {offer_number} von {business_name}',
'email_template_offer_body'       => 'Sehr geehrte/r {client_name},...',
'email_template_invoice_subject'  => 'Rechnung {invoice_number} von {business_name}',
'email_template_invoice_body'     => 'Sehr geehrte/r {client_name},...',
'email_template_reminder_subject' => 'Zahlungserinnerung: Rechnung {invoice_number}',
'email_template_reminder_body'    => 'Sehr geehrte/r {client_name},...',
```

---

## Service Layer

### EmailConfigurationService

Manages SMTP settings and creates dedicated mailer instances to avoid race conditions in queue workers.

```php
namespace App\Services;

use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;

class EmailConfigurationService
{
    public function __construct(
        private SettingsService $settings
    ) {}

    /**
     * Create a dedicated mailer instance with user's SMTP settings.
     * This avoids race conditions when multiple queue workers run simultaneously.
     */
    public function createMailer(): \Illuminate\Mail\Mailer
    {
        $transport = (new EsmtpTransportFactory())->create(new Dsn(
            $this->settings->get('email_encryption') ?: 'smtp',
            $this->settings->get('email_host'),
            $this->settings->get('email_username'),
            $this->decryptPassword(),
            (int) $this->settings->get('email_port', 587)
        ));

        $mailer = new \Illuminate\Mail\Mailer(
            'user-smtp',
            app('view'),
            new \Symfony\Component\Mailer\Mailer($transport)
        );

        $mailer->alwaysFrom(
            $this->settings->get('email_from_address'),
            $this->settings->get('email_from_name')
        );

        return $mailer;
    }

    public function isConfigured(): bool
    {
        return $this->settings->get('email_host')
            && $this->settings->get('email_from_address');
    }

    /**
     * Send test email to verify configuration.
     * @return array{success: bool, message: string}
     */
    public function sendTestEmail(string $toEmail): array
    {
        try {
            $mailer = $this->createMailer();
            $mailer->raw('Dies ist eine Test-E-Mail von Ihrem CRM.', function ($message) use ($toEmail) {
                $message->to($toEmail)->subject('CRM Test-E-Mail');
            });

            return ['success' => true, 'message' => 'Test-E-Mail wurde erfolgreich gesendet.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Fehler: ' . $e->getMessage()];
        }
    }

    private function decryptPassword(): ?string
    {
        $encrypted = $this->settings->get('email_password');
        return $encrypted ? decrypt($encrypted) : null;
    }
}
```

### EmailService

Handles sending all email types with logging.

```php
namespace App\Services;

class EmailService
{
    public function __construct(
        private EmailConfigurationService $config,
        private SettingsService $settings
    ) {}

    /**
     * Send offer email with PDF attachment.
     */
    public function sendOffer(Project $project): EmailLog
    {
        $this->config->configure();

        $log = EmailLog::create([
            'user_id' => auth()->id(),
            'emailable_type' => Project::class,
            'emailable_id' => $project->id,
            'type' => 'offer',
            'recipient_email' => $project->client->email,
            'recipient_name' => $project->client->display_name,
            'subject' => $this->parseTemplate('email_template_offer_subject', $project),
            'body' => $this->parseTemplate('email_template_offer_body', $project),
            'has_attachment' => true,
            'attachment_filename' => "Angebot-{$project->id}.pdf",
            'status' => 'queued',
        ]);

        SendOfferEmail::dispatch($project, $log);

        return $log;
    }

    /**
     * Send invoice email with PDF attachment.
     */
    public function sendInvoice(Invoice $invoice): EmailLog
    {
        // Similar to sendOffer
    }

    /**
     * Send payment reminder for overdue invoice.
     */
    public function sendPaymentReminder(Invoice $invoice): EmailLog
    {
        // Similar pattern, no attachment by default
    }

    /**
     * Parse template placeholders.
     */
    private function parseTemplate(string $templateKey, Model $entity): string
    {
        $template = $this->settings->get($templateKey);

        $placeholders = [
            '{business_name}' => $this->settings->get('business_name'),
            '{client_name}' => $entity->client->display_name,
            '{offer_number}' => $entity->id ?? '',
            '{invoice_number}' => $entity->number ?? '',
            '{invoice_total}' => $entity->formatted_total ?? '',
            '{due_date}' => $entity->due_at?->format('d.m.Y') ?? '',
        ];

        return str_replace(
            array_keys($placeholders),
            array_values($placeholders),
            $template
        );
    }
}
```

---

## Mailables

### OfferMail

```php
namespace App\Mail;

class OfferMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Project $project,
        public string $bodyText
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Angebot von " . config('mail.from.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.offer',
        );
    }

    public function attachments(): array
    {
        $pdf = app(PdfService::class)->generateOfferPdf($this->project);

        return [
            Attachment::fromData(fn () => $pdf->output(), "Angebot-{$this->project->id}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
```

### InvoiceMail & PaymentReminderMail

Similar structure to OfferMail.

---

## Jobs

### SendOfferEmail

```php
namespace App\Jobs;

use App\Mail\OfferMail;
use App\Models\EmailLog;
use App\Models\Project;
use App\Enums\ProjectStatus;
use App\Services\EmailConfigurationService;
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
    public array $backoff = [60, 300, 900]; // 1min, 5min, 15min

    public function __construct(
        public Project $project,
        public EmailLog $emailLog
    ) {}

    public function handle(EmailConfigurationService $config): void
    {
        try {
            // Create dedicated mailer instance (avoids race conditions)
            $mailer = $config->createMailer();

            $mailer->to($this->project->client->email)
                ->send(new OfferMail(
                    $this->project,
                    $this->emailLog->body
                ));

            $this->emailLog->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            // Update project status to 'sent' if still draft
            if ($this->project->status === ProjectStatus::Draft) {
                $this->project->sendOffer();
            }

        } catch (\Exception $e) {
            $this->emailLog->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            // Notify user of failure via Filament database notification
            Notification::make()
                ->title('E-Mail-Versand fehlgeschlagen')
                ->body("Angebot an {$this->project->client->display_name} konnte nicht gesendet werden.")
                ->danger()
                ->sendToDatabase($this->project->user);

            throw $e; // Re-throw for queue retry
        }
    }

    /**
     * Handle job failure after all retries exhausted.
     */
    public function failed(\Throwable $exception): void
    {
        $this->emailLog->update([
            'status' => 'failed',
            'error_message' => 'Alle Versuche fehlgeschlagen: ' . $exception->getMessage(),
        ]);
    }
}
```

---

## Filament Integration

### Settings Page Extension

Add Email Configuration section to existing Settings page:

```php
// In SettingsPage.php, add new section

Section::make('E-Mail-Konfiguration')
    ->description('SMTP-Einstellungen für den E-Mail-Versand')
    ->schema([
        TextInput::make('email_host')
            ->label('SMTP-Server')
            ->placeholder('smtp.example.com'),

        TextInput::make('email_port')
            ->label('Port')
            ->numeric()
            ->default(587),

        Select::make('email_encryption')
            ->label('Verschlüsselung')
            ->options([
                'tls' => 'TLS',
                'ssl' => 'SSL',
                '' => 'Keine',
            ])
            ->default('tls'),

        TextInput::make('email_username')
            ->label('Benutzername'),

        TextInput::make('email_password')
            ->label('Passwort')
            ->password()
            ->dehydrateStateUsing(fn ($state) => $state ? encrypt($state) : null),

        TextInput::make('email_from_name')
            ->label('Absendername')
            ->placeholder('Max Mustermann'),

        TextInput::make('email_from_address')
            ->label('Absenderadresse')
            ->email()
            ->placeholder('rechnung@example.com'),

        Actions::make([
            Action::make('testEmail')
                ->label('Test-E-Mail senden')
                ->icon('heroicon-o-paper-airplane')
                ->requiresConfirmation()
                ->modalHeading('Test-E-Mail senden')
                ->modalDescription('Eine Test-E-Mail wird an Ihre E-Mail-Adresse gesendet.')
                ->action(function () {
                    $result = app(EmailConfigurationService::class)
                        ->sendTestEmail(auth()->user()->email);

                    if ($result['success']) {
                        Notification::make()
                            ->title('Erfolg')
                            ->body($result['message'])
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Fehler')
                            ->body($result['message'])
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
        ]),
    ]),

Section::make('E-Mail-Vorlagen')
    ->description('Vorlagen für automatische E-Mails. Platzhalter: {business_name}, {client_name}, {invoice_number}, {invoice_total}, {due_date}')
    ->schema([
        TextInput::make('email_template_offer_subject')
            ->label('Angebot: Betreff')
            ->default('Angebot von {business_name}'),

        Textarea::make('email_template_offer_body')
            ->label('Angebot: Text')
            ->rows(5),

        // Similar for invoice and reminder templates
    ]),
```

### Project Resource - Send Offer Action

```php
// In ProjectResource/Pages/EditProject.php or as table action

Action::make('sendOfferEmail')
    ->label('Per E-Mail senden')
    ->icon('heroicon-o-envelope')
    ->color('success')
    ->requiresConfirmation()
    ->modalHeading('Angebot per E-Mail senden')
    ->modalDescription(fn (Project $record) =>
        "Das Angebot wird an {$record->client->email} gesendet.")
    ->visible(fn (Project $record) =>
        app(EmailConfigurationService::class)->isConfigured())
    ->disabled(fn (Project $record) => !$record->client->email)
    ->tooltip(fn (Project $record) => !$record->client->email
        ? 'Der Kunde hat keine E-Mail-Adresse hinterlegt'
        : null)
    ->action(function (Project $record): void {
        if (!$record->client->email) {
            Notification::make()
                ->title('Keine E-Mail-Adresse')
                ->body('Bitte hinterlegen Sie eine E-Mail-Adresse beim Kunden.')
                ->warning()
                ->actions([
                    \Filament\Notifications\Actions\Action::make('editClient')
                        ->label('Kunde bearbeiten')
                        ->url(ClientResource::getUrl('edit', ['record' => $record->client])),
                ])
                ->send();
            return;
        }

        $log = app(EmailService::class)->sendOffer($record);

        Notification::make()
            ->title('E-Mail wird gesendet')
            ->body("Das Angebot wird an {$record->client->email} gesendet.")
            ->success()
            ->send();
    }),
```

### Invoice Resource - Send Invoice Action

Similar to Project, but for invoices.

### EmailLog Relation Manager

Show sent emails on Project and Invoice edit pages with retry capability:

```php
// App\Filament\Resources\ProjectResource\RelationManagers\EmailLogsRelationManager

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Jobs\SendOfferEmail;
use App\Models\EmailLog;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmailLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'emailLogs';
    protected static ?string $title = 'Gesendete E-Mails';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('Typ')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'offer' => 'Angebot',
                        'invoice' => 'Rechnung',
                        'payment_reminder' => 'Zahlungserinnerung',
                        default => $state,
                    }),

                TextColumn::make('recipient_email')
                    ->label('Empfänger'),

                TextColumn::make('subject')
                    ->label('Betreff')
                    ->limit(50),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sent' => 'success',
                        'failed' => 'danger',
                        default => 'warning',
                    }),

                TextColumn::make('sent_at')
                    ->label('Gesendet')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('Ausstehend'),

                TextColumn::make('error_message')
                    ->label('Fehler')
                    ->limit(30)
                    ->tooltip(fn (EmailLog $record) => $record->error_message)
                    ->visible(fn (EmailLog $record) => $record->status === 'failed'),
            ])
            ->actions([
                Action::make('retry')
                    ->label('Erneut senden')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (EmailLog $record) => $record->status === 'failed')
                    ->requiresConfirmation()
                    ->action(function (EmailLog $record): void {
                        // Reset status and dispatch new job
                        $record->update(['status' => 'queued', 'error_message' => null]);

                        if ($record->type === 'offer') {
                            SendOfferEmail::dispatch($record->emailable, $record);
                        }
                        // Add other types as needed

                        Notification::make()
                            ->title('E-Mail wird erneut gesendet')
                            ->success()
                            ->send();
                    }),

                Action::make('viewError')
                    ->label('Fehler anzeigen')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->visible(fn (EmailLog $record) => $record->status === 'failed' && $record->error_message)
                    ->modalHeading('Fehlermeldung')
                    ->modalContent(fn (EmailLog $record) => view('filament.modals.email-error', ['log' => $record]))
                    ->modalSubmitAction(false),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
```

---

## Email Templates (Blade Views)

### resources/views/emails/offer.blade.php

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    {!! nl2br(e($bodyText)) !!}

    <p style="margin-top: 30px; color: #666; font-size: 12px;">
        Das Angebot finden Sie im Anhang als PDF.
    </p>
</body>
</html>
```

Similar templates for `invoice.blade.php` and `payment-reminder.blade.php`.

---

## Security Considerations

1. **Password Encryption**: SMTP password stored encrypted using Laravel's `encrypt()` helper
2. **Email Validation**: Validate recipient email before attempting to send
3. **Rate Limiting**: Consider adding rate limiting for email sending to prevent abuse
4. **Queue Monitoring**: Monitor failed jobs for email delivery issues

---

## Testing

### Unit Tests

```php
// tests/Unit/Services/EmailServiceTest.php

it('parses template placeholders correctly', function () {
    // Test placeholder replacement
});

it('creates email log when sending offer', function () {
    // Test log creation
});
```

### Feature Tests

```php
// tests/Feature/EmailIntegrationTest.php

it('can send offer email via action', function () {
    // Mock mail, test action
});

it('logs failed email attempts', function () {
    // Test error handling
});

it('requires email configuration before sending', function () {
    // Test configuration check
});
```

---

## Migration Path

1. Create `email_logs` migration
2. Create EmailLog model with global user scope
3. Create EmailLogFactory for testing
4. Add email settings fields to Settings page
5. Create Mailables (OfferMail, InvoiceMail, PaymentReminderMail)
6. Create Jobs (SendOfferEmail, SendInvoiceEmail, SendPaymentReminder)
7. Create EmailService and EmailConfigurationService
8. Add `emailLogs()` polymorphic relation to Project and Invoice models
9. Add send actions to Project and Invoice resources
10. Add EmailLogsRelationManager to both resources
11. Create error modal view (`resources/views/filament/modals/email-error.blade.php`)
12. Write tests (unit + feature + queue failure handling)
13. Update user documentation
