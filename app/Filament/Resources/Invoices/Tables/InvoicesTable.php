<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label('Nr.')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('client.display_name')
                    ->label('Kunde')
                    ->searchable(['client.company_name', 'client.contact_name']),

                TextColumn::make('issued_at')
                    ->label('Datum')
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('due_at')
                    ->label('Fällig')
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('total')
                    ->label('Betrag')
                    ->money('EUR')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),

                TextColumn::make('paid_at')
                    ->label('Bezahlt')
                    ->date('d.m.Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('issued_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(InvoiceStatus::class),

                Filter::make('issued_at')
                    ->form([
                        DatePicker::make('from')->label('Von'),
                        DatePicker::make('until')->label('Bis'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('issued_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('issued_at', '<=', $date));
                    }),

                SelectFilter::make('year')
                    ->label('Jahr')
                    ->options(fn () => Invoice::query()
                        ->selectRaw('YEAR(issued_at) as year')
                        ->distinct()
                        ->orderByDesc('year')
                        ->pluck('year', 'year')
                        ->toArray())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'], fn ($q, $year) => $q->whereYear('issued_at', $year))),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('markPaid')
                    ->label('Bezahlt')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->form([
                        DatePicker::make('paid_at')
                            ->label('Zahlungsdatum')
                            ->default(now())
                            ->required(),
                        TextInput::make('payment_method')
                            ->label('Zahlungsart'),
                    ])
                    ->action(fn (Invoice $record, array $data) => $record->markAsPaid($data))
                    ->visible(fn (Invoice $record) => $record->status === InvoiceStatus::Sent || $record->status === InvoiceStatus::Overdue),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
