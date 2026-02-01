<?php

namespace App\Filament\Resources\Clients\Schemas;

use App\Enums\ClientType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Kontaktdaten')
                    ->schema([
                        Select::make('type')
                            ->label('Typ')
                            ->options(ClientType::class)
                            ->default(ClientType::Company)
                            ->required()
                            ->live()
                            ->columnSpan(1),

                        TextInput::make('company_name')
                            ->label('Firmenname')
                            ->visible(fn ($get) => $get('type') === ClientType::Company->value || $get('type') === ClientType::Company)
                            ->required(fn ($get) => $get('type') === ClientType::Company->value || $get('type') === ClientType::Company)
                            ->maxLength(255)
                            ->columnSpan(1),

                        TextInput::make('contact_name')
                            ->label('Ansprechpartner')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('E-Mail')
                            ->email()
                            ->maxLength(255),

                        TextInput::make('phone')
                            ->label('Telefon')
                            ->tel()
                            ->maxLength(50),

                        TextInput::make('vat_id')
                            ->label('USt-IdNr.')
                            ->visible(fn ($get) => $get('type') === ClientType::Company->value || $get('type') === ClientType::Company)
                            ->maxLength(50),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Adresse')
                    ->schema([
                        TextInput::make('street')
                            ->label('Straße')
                            ->maxLength(255),

                        TextInput::make('postal_code')
                            ->label('PLZ')
                            ->maxLength(10),

                        TextInput::make('city')
                            ->label('Ort')
                            ->maxLength(255),

                        Select::make('country')
                            ->label('Land')
                            ->options([
                                'DE' => 'Deutschland',
                                'AT' => 'Österreich',
                                'CH' => 'Schweiz',
                            ])
                            ->default('DE'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Notizen')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Notizen')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->columnSpanFull(),
            ]);
    }
}
