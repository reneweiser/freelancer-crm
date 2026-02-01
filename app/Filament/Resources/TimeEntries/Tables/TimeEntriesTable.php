<?php

namespace App\Filament\Resources\TimeEntries\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TimeEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('started_at')
                    ->label('Datum')
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('project.title')
                    ->label('Projekt')
                    ->searchable()
                    ->limit(30),

                TextColumn::make('project.client.display_name')
                    ->label('Kunde')
                    ->searchable(['project.client.company_name', 'project.client.contact_name'])
                    ->toggleable(),

                TextColumn::make('description')
                    ->label('Beschreibung')
                    ->limit(40)
                    ->searchable(),

                TextColumn::make('started_at')
                    ->label('Beginn')
                    ->time('H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('ended_at')
                    ->label('Ende')
                    ->time('H:i')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('formatted_duration')
                    ->label('Dauer')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('duration_minutes', $direction)),

                IconColumn::make('billable')
                    ->label('Abr.')
                    ->boolean()
                    ->trueIcon('heroicon-o-currency-euro')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),

                IconColumn::make('invoice_id')
                    ->label('Verr.')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->invoice_id !== null)
                    ->trueIcon('heroicon-o-document-check')
                    ->falseIcon('heroicon-o-document')
                    ->trueColor('success')
                    ->falseColor('gray'),
            ])
            ->defaultSort('started_at', 'desc')
            ->filters([
                SelectFilter::make('project_id')
                    ->label('Projekt')
                    ->relationship('project', 'title')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('billable')
                    ->label('Abrechenbar')
                    ->placeholder('Alle')
                    ->trueLabel('Nur abrechenbar')
                    ->falseLabel('Nur nicht abrechenbar'),

                TernaryFilter::make('invoiced')
                    ->label('Verrechnet')
                    ->placeholder('Alle')
                    ->trueLabel('Verrechnet')
                    ->falseLabel('Nicht verrechnet')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('invoice_id'),
                        false: fn (Builder $query) => $query->whereNull('invoice_id'),
                    ),

                Filter::make('date_range')
                    ->form([
                        DatePicker::make('from')->label('Von'),
                        DatePicker::make('until')->label('Bis'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('started_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('started_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
