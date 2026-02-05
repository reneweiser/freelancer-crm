<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Projektdaten')
                    ->schema([
                        Select::make('client_id')
                            ->label('Kunde')
                            ->relationship('client', 'company_name')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->display_name)
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('title')
                            ->label('Titel')
                            ->required()
                            ->maxLength(255),

                        Textarea::make('description')
                            ->label('Beschreibung')
                            ->rows(3)
                            ->columnSpanFull(),

                        TextInput::make('reference')
                            ->label('Referenz')
                            ->helperText('Interne Referenznummer')
                            ->maxLength(50),

                        Select::make('type')
                            ->label('Abrechnungsart')
                            ->options(ProjectType::class)
                            ->default(ProjectType::Fixed)
                            ->required()
                            ->live(),

                        TextInput::make('hourly_rate')
                            ->label('Stundensatz')
                            ->helperText('Wird für die Abrechnung von Zeiterfassungen verwendet')
                            ->numeric()
                            ->prefix('€')
                            ->live()
                            ->visible(fn ($get) => $get('type') === ProjectType::Hourly->value || $get('type') === ProjectType::Hourly),

                        TextInput::make('fixed_price')
                            ->label('Festpreis')
                            ->numeric()
                            ->prefix('€')
                            ->visible(fn ($get) => $get('type') === ProjectType::Fixed->value || $get('type') === ProjectType::Fixed),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Status & Termine')
                    ->schema([
                        Select::make('status')
                            ->label('Status')
                            ->options(ProjectStatus::class)
                            ->default(ProjectStatus::Draft)
                            ->required(),

                        DatePicker::make('offer_date')
                            ->label('Angebotsdatum')
                            ->default(now()),

                        DatePicker::make('offer_valid_until')
                            ->label('Angebot gültig bis')
                            ->default(now()->addDays(30)),

                        DatePicker::make('start_date')
                            ->label('Projektstart'),

                        DatePicker::make('end_date')
                            ->label('Projektende'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Positionen')
                    ->schema([
                        Repeater::make('items')
                            ->label('')
                            ->relationship()
                            ->schema([
                                TextInput::make('description')
                                    ->label('Beschreibung')
                                    ->required()
                                    ->columnSpan(2),

                                TextInput::make('quantity')
                                    ->label('Menge')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(0.01)
                                    ->columnSpan(1),

                                Select::make('unit')
                                    ->label('Einheit')
                                    ->options([
                                        'Stück' => 'Stück',
                                        'Stunden' => 'Stunden',
                                        'Tage' => 'Tage',
                                        'Pauschal' => 'Pauschal',
                                    ])
                                    ->default('Stück')
                                    ->live()
                                    ->afterStateUpdated(function ($state, $set, $get) {
                                        if ($state === 'Stunden' && ! $get('unit_price')) {
                                            $hourlyRate = $get('../../hourly_rate');
                                            if ($hourlyRate) {
                                                $set('unit_price', $hourlyRate);
                                            }
                                        }
                                    })
                                    ->columnSpan(1),

                                TextInput::make('unit_price')
                                    ->label('Einzelpreis')
                                    ->helperText(fn ($get) => $get('unit') === 'Stunden'
                                        ? 'Kann vom Projekt-Stundensatz abweichen'
                                        : null)
                                    ->numeric()
                                    ->prefix('€')
                                    ->required()
                                    ->columnSpan(1),
                            ])
                            ->columns(5)
                            ->reorderable()
                            ->orderColumn('position')
                            ->collapsible()
                            ->live()
                            ->defaultItems(1)
                            ->addActionLabel('Position hinzufügen')
                            ->columnSpanFull(),

                        Placeholder::make('items_total')
                            ->label('Angebotssumme (netto)')
                            ->content(function ($get) {
                                $items = $get('items') ?? [];
                                $total = collect($items)->sum(function ($item) {
                                    return ((float) ($item['quantity'] ?? 0)) * ((float) ($item['unit_price'] ?? 0));
                                });

                                return Number::currency($total, 'EUR', 'de');
                            })
                            ->visible(fn ($get) => ! empty($get('items'))),
                    ])
                    ->columnSpanFull(),

                Section::make('Notizen')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Interne Notizen')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->columnSpanFull(),
            ]);
    }
}
