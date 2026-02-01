<?php

namespace App\Filament\Resources\Invoices\Schemas;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Rechnungsdaten')
                    ->schema([
                        Select::make('client_id')
                            ->label('Kunde')
                            ->relationship('client', 'company_name')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->display_name)
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),

                        Select::make('project_id')
                            ->label('Projekt (optional)')
                            ->relationship(
                                'project',
                                'title',
                                fn ($query, $get) => $query->where('client_id', $get('client_id'))
                            )
                            ->searchable()
                            ->preload()
                            ->visible(fn ($get) => filled($get('client_id'))),

                        TextInput::make('number')
                            ->label('Rechnungsnummer')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(fn () => Invoice::generateNextNumber()),

                        Select::make('status')
                            ->label('Status')
                            ->options(InvoiceStatus::class)
                            ->default(InvoiceStatus::Draft)
                            ->required(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Datum')
                    ->schema([
                        DatePicker::make('issued_at')
                            ->label('Rechnungsdatum')
                            ->required()
                            ->default(now()),

                        DatePicker::make('due_at')
                            ->label('Fällig am')
                            ->required()
                            ->default(now()->addDays(14)),

                        DatePicker::make('service_period_start')
                            ->label('Leistungszeitraum von'),

                        DatePicker::make('service_period_end')
                            ->label('Leistungszeitraum bis'),

                        DatePicker::make('paid_at')
                            ->label('Bezahlt am')
                            ->visible(fn ($get) => $get('status') === InvoiceStatus::Paid->value || $get('status') === InvoiceStatus::Paid),

                        TextInput::make('payment_method')
                            ->label('Zahlungsart')
                            ->visible(fn ($get) => $get('status') === InvoiceStatus::Paid->value || $get('status') === InvoiceStatus::Paid),
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
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, $set, $get) => self::updateTotals($set, $get))
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
                                    ->columnSpan(1),

                                TextInput::make('unit_price')
                                    ->label('Einzelpreis')
                                    ->numeric()
                                    ->prefix('€')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, $set, $get) => self::updateTotals($set, $get))
                                    ->columnSpan(1),

                                Select::make('vat_rate')
                                    ->label('MwSt.')
                                    ->options([
                                        '19.00' => '19%',
                                        '7.00' => '7%',
                                        '0.00' => '0%',
                                    ])
                                    ->default('19.00')
                                    ->columnSpan(1),
                            ])
                            ->columns(6)
                            ->reorderable()
                            ->orderColumn('position')
                            ->defaultItems(1)
                            ->addActionLabel('Position hinzufügen')
                            ->live()
                            ->afterStateUpdated(fn ($state, $set, $get) => self::updateTotals($set, $get))
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Summen')
                    ->schema([
                        Placeholder::make('subtotal_display')
                            ->label('Netto')
                            ->content(fn ($get) => Number::currency((float) ($get('subtotal') ?? 0), 'EUR', 'de_DE')),

                        TextInput::make('vat_rate')
                            ->label('MwSt.-Satz')
                            ->numeric()
                            ->suffix('%')
                            ->default(19.00)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, $set, $get) => self::updateTotals($set, $get)),

                        Placeholder::make('vat_display')
                            ->label('MwSt.-Betrag')
                            ->content(fn ($get) => Number::currency((float) ($get('vat_amount') ?? 0), 'EUR', 'de_DE')),

                        Placeholder::make('total_display')
                            ->label('Brutto')
                            ->content(fn ($get) => Number::currency((float) ($get('total') ?? 0), 'EUR', 'de_DE')),

                        Hidden::make('subtotal')->default(0),
                        Hidden::make('vat_amount')->default(0),
                        Hidden::make('total')->default(0),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),

                Section::make('Zusätzliche Angaben')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Bemerkungen')
                            ->rows(2),

                        Textarea::make('footer_text')
                            ->label('Fußzeile')
                            ->rows(2)
                            ->default('Bitte überweisen Sie den Betrag innerhalb von 14 Tagen.'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->columnSpanFull(),
            ]);
    }

    public static function updateTotals($set, $get): void
    {
        $items = $get('items') ?? [];
        $vatRate = (float) ($get('vat_rate') ?? 19);

        $subtotal = 0;
        foreach ($items as $item) {
            $quantity = (float) ($item['quantity'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $subtotal += $quantity * $unitPrice;
        }

        $vatAmount = $subtotal * ($vatRate / 100);
        $total = $subtotal + $vatAmount;

        $set('subtotal', round($subtotal, 2));
        $set('vat_amount', round($vatAmount, 2));
        $set('total', round($total, 2));
    }
}
