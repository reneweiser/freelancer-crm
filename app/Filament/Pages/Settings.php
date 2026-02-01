<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

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
}
