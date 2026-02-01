<?php

namespace App\Filament\Exports;

use App\Models\Invoice;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;

class InvoiceExporter extends Exporter
{
    protected static ?string $model = Invoice::class;

    /**
     * @return array<ExportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('number')
                ->label('Rechnungsnummer'),

            ExportColumn::make('status')
                ->label('Status')
                ->formatStateUsing(fn ($state): string => $state?->getLabel() ?? ''),

            ExportColumn::make('client.display_name')
                ->label('Kunde'),

            ExportColumn::make('issued_at')
                ->label('Rechnungsdatum')
                ->formatStateUsing(fn ($state): string => $state?->format('d.m.Y') ?? ''),

            ExportColumn::make('due_at')
                ->label('Fälligkeitsdatum')
                ->formatStateUsing(fn ($state): string => $state?->format('d.m.Y') ?? ''),

            ExportColumn::make('paid_at')
                ->label('Bezahlt am')
                ->formatStateUsing(fn ($state): string => $state?->format('d.m.Y') ?? ''),

            ExportColumn::make('payment_method')
                ->label('Zahlungsart'),

            ExportColumn::make('service_period_start')
                ->label('Leistungszeitraum Start')
                ->formatStateUsing(fn ($state): string => $state?->format('d.m.Y') ?? ''),

            ExportColumn::make('service_period_end')
                ->label('Leistungszeitraum Ende')
                ->formatStateUsing(fn ($state): string => $state?->format('d.m.Y') ?? ''),

            ExportColumn::make('subtotal')
                ->label('Nettobetrag')
                ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.')),

            ExportColumn::make('vat_rate')
                ->label('MwSt.-Satz')
                ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.').' %'),

            ExportColumn::make('vat_amount')
                ->label('MwSt.-Betrag')
                ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.')),

            ExportColumn::make('total')
                ->label('Bruttobetrag')
                ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.')),
        ];
    }

    /**
     * @return array<\Filament\Schemas\Components\Component>
     */
    public static function getOptionsFormComponents(): array
    {
        return [
            DatePicker::make('from')
                ->label('Von'),
            DatePicker::make('until')
                ->label('Bis'),
        ];
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['client']);
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $count = number_format($export->successful_rows);

        return "Der Export von {$count} Rechnungen wurde abgeschlossen.";
    }
}
