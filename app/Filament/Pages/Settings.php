<?php

namespace App\Filament\Pages;

use App\Services\EmailConfigurationService;
use App\Services\SettingsService;
use App\Services\WebhookService;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

/**
 * @property-read Schema $form
 */
class Settings extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Einstellungen';

    protected static ?string $title = 'Einstellungen';

    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.pages.settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $settings = Auth::user()->settingsService()->getAll();
        $this->form->fill($settings);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Section::make('Geschaeftsdaten')
                        ->label('Geschäftsdaten')
                        ->description('Ihre Firmen- oder Freiberuflerangaben')
                        ->schema([
                            TextInput::make('business_name')
                                ->label('Firmenname')
                                ->maxLength(255),
                            Textarea::make('business_address')
                                ->label('Adresse')
                                ->rows(3)
                                ->helperText('Strasse, PLZ, Ort'),
                            TextInput::make('business_email')
                                ->label('E-Mail')
                                ->email()
                                ->maxLength(255),
                            TextInput::make('business_phone')
                                ->label('Telefon')
                                ->tel()
                                ->maxLength(50),
                        ])
                        ->columns(2),

                    Section::make('Steuerangaben')
                        ->description('Steuerliche Informationen für Rechnungen')
                        ->schema([
                            TextInput::make('tax_number')
                                ->label('Steuernummer')
                                ->maxLength(50),
                            TextInput::make('vat_id')
                                ->label('USt-IdNr.')
                                ->maxLength(50),
                            TextInput::make('default_vat_rate')
                                ->label('Standard-MwSt.-Satz')
                                ->numeric()
                                ->suffix('%')
                                ->default('19.00')
                                ->maxLength(10),
                        ])
                        ->columns(3),

                    Section::make('Bankverbindung')
                        ->description('Bankdaten für Zahlungen')
                        ->schema([
                            TextInput::make('bank_name')
                                ->label('Bank')
                                ->maxLength(255),
                            TextInput::make('iban')
                                ->label('IBAN')
                                ->maxLength(34),
                            TextInput::make('bic')
                                ->label('BIC')
                                ->maxLength(11),
                        ])
                        ->columns(3),

                    Section::make('Rechnungseinstellungen')
                        ->description('Standardwerte für neue Rechnungen')
                        ->schema([
                            TextInput::make('invoice_prefix')
                                ->label('Rechnungsnummer-Präfix')
                                ->helperText('Optional, z.B. "RE-" für RE-2026-001')
                                ->maxLength(20),
                            TextInput::make('payment_terms_days')
                                ->label('Zahlungsziel (Tage)')
                                ->numeric()
                                ->default(14)
                                ->minValue(0)
                                ->maxValue(365),
                            Textarea::make('invoice_footer')
                                ->label('Rechnungsfusszeile')
                                ->rows(3)
                                ->helperText('Wird am Ende jeder Rechnung angezeigt')
                                ->columnSpanFull(),
                        ])
                        ->columns(2),

                    Section::make('E-Mail-Konfiguration')
                        ->description('SMTP-Einstellungen für den E-Mail-Versand')
                        ->schema([
                            TextInput::make('email_host')
                                ->label('SMTP-Server')
                                ->placeholder('smtp.example.com')
                                ->maxLength(255),
                            TextInput::make('email_port')
                                ->label('Port')
                                ->numeric()
                                ->default(587)
                                ->minValue(1)
                                ->maxValue(65535),
                            Select::make('email_encryption')
                                ->label('Verschlüsselung')
                                ->options([
                                    'tls' => 'TLS (empfohlen)',
                                    'ssl' => 'SSL',
                                    '' => 'Keine',
                                ])
                                ->default('tls'),
                            TextInput::make('email_username')
                                ->label('Benutzername')
                                ->maxLength(255),
                            TextInput::make('email_password')
                                ->label('Passwort')
                                ->password()
                                ->revealable()
                                ->formatStateUsing(fn () => null)
                                ->dehydrateStateUsing(fn ($state) => $state
                                    ? encrypt($state)
                                    : Auth::user()->settingsService()->get('email_password'))
                                ->maxLength(255)
                                ->placeholder('••••••••'),
                            TextInput::make('email_from_name')
                                ->label('Absendername')
                                ->placeholder('Max Mustermann')
                                ->maxLength(255),
                            TextInput::make('email_from_address')
                                ->label('Absenderadresse')
                                ->email()
                                ->placeholder('rechnung@example.com')
                                ->maxLength(255),
                            Actions::make([
                                Action::make('testEmail')
                                    ->label('Test-E-Mail senden')
                                    ->icon('heroicon-o-paper-airplane')
                                    ->color('gray')
                                    ->requiresConfirmation()
                                    ->modalHeading('Test-E-Mail senden')
                                    ->modalDescription('Eine Test-E-Mail wird an Ihre Benutzer-E-Mail-Adresse gesendet.')
                                    ->action(function () {
                                        // First save the current settings
                                        $data = $this->form->getState();
                                        Auth::user()->settingsService()->setMany($data);

                                        $settings = new SettingsService(Auth::user());
                                        $config = new EmailConfigurationService($settings);
                                        $result = $config->sendTestEmail(Auth::user()->email);

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
                            ])->columnSpanFull(),
                        ])
                        ->columns(2),

                    Section::make('E-Mail-Vorlagen')
                        ->description('Vorlagen für automatische E-Mails. Platzhalter: {business_name}, {client_name}, {invoice_number}, {invoice_total}, {due_date}, {offer_number}')
                        ->collapsed()
                        ->schema([
                            TextInput::make('email_template_offer_subject')
                                ->label('Angebot: Betreff')
                                ->placeholder('Angebot von {business_name}')
                                ->maxLength(255),
                            Textarea::make('email_template_offer_body')
                                ->label('Angebot: Text')
                                ->rows(4)
                                ->placeholder("Sehr geehrte/r {client_name},\n\nanbei erhalten Sie unser Angebot.\n\nMit freundlichen Grüßen\n{business_name}"),
                            TextInput::make('email_template_invoice_subject')
                                ->label('Rechnung: Betreff')
                                ->placeholder('Rechnung {invoice_number} von {business_name}')
                                ->maxLength(255),
                            Textarea::make('email_template_invoice_body')
                                ->label('Rechnung: Text')
                                ->rows(4)
                                ->placeholder("Sehr geehrte/r {client_name},\n\nanbei erhalten Sie die Rechnung {invoice_number}.\n\nBitte überweisen Sie den Betrag von {invoice_total} bis zum {due_date}.\n\nMit freundlichen Grüßen\n{business_name}"),
                            TextInput::make('email_template_reminder_subject')
                                ->label('Zahlungserinnerung: Betreff')
                                ->placeholder('Zahlungserinnerung: Rechnung {invoice_number}')
                                ->maxLength(255),
                            Textarea::make('email_template_reminder_body')
                                ->label('Zahlungserinnerung: Text')
                                ->rows(4)
                                ->placeholder("Sehr geehrte/r {client_name},\n\nwir möchten Sie freundlich an die ausstehende Zahlung für Rechnung {invoice_number} erinnern.\n\nMit freundlichen Grüßen\n{business_name}"),
                        ])
                        ->columns(2),

                    Section::make('Webhooks')
                        ->description('Sende Benachrichtigungen an externe Systeme wie n8n oder Zapier.')
                        ->icon('heroicon-o-globe-alt')
                        ->collapsed()
                        ->schema([
                            Toggle::make('webhook_enabled')
                                ->label('Webhook aktivieren')
                                ->helperText('Sendet eine HTTP-Anfrage wenn Erinnerungen fällig werden.')
                                ->live(),

                            TextInput::make('webhook_url')
                                ->label('Webhook URL')
                                ->url()
                                ->placeholder('https://n8n.example.com/webhook/abc123')
                                ->helperText('Die URL die aufgerufen wird (POST-Anfrage).')
                                ->visible(fn (Get $get): bool => (bool) $get('webhook_enabled')),

                            TextInput::make('webhook_secret')
                                ->label('Webhook Secret')
                                ->password()
                                ->revealable()
                                ->formatStateUsing(fn () => null)
                                ->dehydrateStateUsing(fn ($state) => $state
                                    ? encrypt($state)
                                    : Auth::user()->settingsService()->get('webhook_secret'))
                                ->helperText('Geheimer Schlüssel zur Signatur-Verifizierung (HMAC-SHA256).')
                                ->placeholder('••••••••')
                                ->visible(fn (Get $get): bool => (bool) $get('webhook_enabled')),

                            Placeholder::make('webhook_status')
                                ->label('Letzter Status')
                                ->content(fn (): HtmlString => $this->getWebhookStatusDisplay())
                                ->visible(fn (Get $get): bool => (bool) $get('webhook_enabled')),

                            Actions::make([
                                Action::make('testWebhook')
                                    ->label('Webhook testen')
                                    ->icon('heroicon-o-paper-airplane')
                                    ->color('gray')
                                    ->action(fn () => $this->testWebhook()),
                            ])
                                ->visible(fn (Get $get): bool => (bool) $get('webhook_enabled') && ! empty($get('webhook_url')))
                                ->columnSpanFull(),
                        ])
                        ->columns(2),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Speichern')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Auth::user()->settingsService()->setMany($data);

        Notification::make()
            ->success()
            ->title('Einstellungen gespeichert')
            ->send();
    }

    protected function getWebhookStatusDisplay(): HtmlString
    {
        $settings = new SettingsService(Auth::user());
        $webhookService = new WebhookService($settings);
        $status = $webhookService->getLastStatus();

        if (! $status) {
            return new HtmlString('<span class="text-gray-500">Noch kein Webhook gesendet</span>');
        }

        $timestamp = $status['sent_at']
            ? \Carbon\Carbon::parse($status['sent_at'])->format('d.m.Y H:i:s')
            : '';

        if ($status['status'] === 'success') {
            return new HtmlString(
                '<span class="text-success-600 dark:text-success-400">Erfolgreich</span>'.
                ($timestamp ? ' <span class="text-gray-500 text-sm">('.$timestamp.')</span>' : '')
            );
        }

        $error = htmlspecialchars($status['error'] ?? 'Unbekannter Fehler');

        return new HtmlString(
            '<span class="text-danger-600 dark:text-danger-400">Fehler</span>'.
            ($timestamp ? ' <span class="text-gray-500 text-sm">('.$timestamp.')</span>' : '').
            '<br><span class="text-sm text-gray-500">'.$error.'</span>'
        );
    }

    protected function testWebhook(): void
    {
        $data = $this->form->getState();
        Auth::user()->settingsService()->setMany($data);

        $settings = new SettingsService(Auth::user());
        $webhookService = new WebhookService($settings);

        if (! $webhookService->isEnabled()) {
            Notification::make()
                ->title('Fehler')
                ->body('Webhook ist nicht aktiviert oder keine URL konfiguriert.')
                ->danger()
                ->send();

            return;
        }

        $result = $webhookService->sendTestWebhook();

        if ($result) {
            Notification::make()
                ->title('Erfolg')
                ->body('Test-Webhook wurde erfolgreich gesendet.')
                ->success()
                ->send();
        } else {
            $status = $webhookService->getLastStatus();
            Notification::make()
                ->title('Fehler')
                ->body('Webhook konnte nicht gesendet werden: '.($status['error'] ?? 'Unbekannter Fehler'))
                ->danger()
                ->persistent()
                ->send();
        }
    }
}
