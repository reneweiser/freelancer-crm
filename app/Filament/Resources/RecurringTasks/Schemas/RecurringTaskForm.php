<?php

namespace App\Filament\Resources\RecurringTasks\Schemas;

use App\Enums\TaskFrequency;
use App\Models\Client;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RecurringTaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Aufgabe')
                    ->schema([
                        TextInput::make('title')
                            ->label('Titel')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('z.B. Website-Wartung, Hosting-Verlängerung'),

                        Textarea::make('description')
                            ->label('Beschreibung')
                            ->rows(3)
                            ->placeholder('Details zur Aufgabe...'),

                        Select::make('client_id')
                            ->label('Kunde')
                            ->relationship('client', 'company_name')
                            ->getOptionLabelFromRecordUsing(fn (Client $record): string => $record->display_name)
                            ->searchable()
                            ->preload()
                            ->placeholder('Kein Kunde zugeordnet'),
                    ]),

                Section::make('Zeitplan')
                    ->columns(2)
                    ->schema([
                        Select::make('frequency')
                            ->label('Frequenz')
                            ->options(TaskFrequency::class)
                            ->required()
                            ->default(TaskFrequency::Monthly),

                        DatePicker::make('next_due_at')
                            ->label('Nächste Fälligkeit')
                            ->required()
                            ->native(false)
                            ->displayFormat('d.m.Y')
                            ->default(now()->addMonth()),

                        DatePicker::make('started_at')
                            ->label('Vertragsbeginn')
                            ->native(false)
                            ->displayFormat('d.m.Y'),

                        DatePicker::make('ends_at')
                            ->label('Vertragsende')
                            ->native(false)
                            ->displayFormat('d.m.Y')
                            ->helperText('Leer lassen für unbefristete Aufgaben'),
                    ]),

                Section::make('Abrechnung')
                    ->columns(2)
                    ->schema([
                        TextInput::make('amount')
                            ->label('Betrag')
                            ->numeric()
                            ->prefix('€')
                            ->placeholder('0,00'),

                        TextInput::make('billing_notes')
                            ->label('Abrechnungsnotizen')
                            ->placeholder('z.B. Monatliche Pauschale'),
                    ]),

                Section::make('Status')
                    ->schema([
                        Toggle::make('active')
                            ->label('Aktiv')
                            ->default(true)
                            ->helperText('Inaktive Aufgaben werden nicht verarbeitet'),
                    ]),
            ]);
    }
}
