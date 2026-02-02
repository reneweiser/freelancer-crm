<?php

namespace App\Filament\Resources\RecurringTasks\Tables;

use App\Enums\TaskFrequency;
use App\Models\Client;
use App\Models\RecurringTask;
use App\Services\RecurringTaskService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class RecurringTasksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Aufgabe')
                    ->searchable()
                    ->sortable()
                    ->description(fn (RecurringTask $record) => $record->client?->display_name),

                TextColumn::make('frequency')
                    ->label('Frequenz')
                    ->badge(),

                TextColumn::make('next_due_at')
                    ->label('Nächste Fälligkeit')
                    ->date('d.m.Y')
                    ->sortable()
                    ->color(fn (RecurringTask $record) => match (true) {
                        $record->is_overdue => 'danger',
                        $record->is_due_soon => 'warning',
                        default => null,
                    }),

                TextColumn::make('amount')
                    ->label('Betrag')
                    ->money('EUR', locale: 'de')
                    ->placeholder('-'),

                IconColumn::make('active')
                    ->label('Aktiv')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('frequency')
                    ->label('Frequenz')
                    ->options(TaskFrequency::class),

                SelectFilter::make('client')
                    ->label('Kunde')
                    ->relationship('client', 'company_name')
                    ->getOptionLabelFromRecordUsing(fn (Client $record): string => $record->display_name),

                TernaryFilter::make('active')
                    ->label('Status')
                    ->placeholder('Alle')
                    ->trueLabel('Nur aktive')
                    ->falseLabel('Nur inaktive'),
            ])
            ->recordActions([
                Action::make('process')
                    ->label('Jetzt ausführen')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn (RecurringTask $record) => $record->active && $record->is_overdue)
                    ->requiresConfirmation()
                    ->action(function (RecurringTask $record) {
                        app(RecurringTaskService::class)->processTask($record);

                        Notification::make()
                            ->title('Aufgabe verarbeitet')
                            ->body('Erinnerung wurde erstellt und Aufgabe weitergeschaltet.')
                            ->success()
                            ->send();
                    }),

                Action::make('skip')
                    ->label('Überspringen')
                    ->icon('heroicon-o-forward')
                    ->color('warning')
                    ->visible(fn (RecurringTask $record) => $record->active)
                    ->form([
                        Textarea::make('reason')
                            ->label('Grund (optional)')
                            ->rows(2),
                    ])
                    ->action(function (RecurringTask $record, array $data) {
                        app(RecurringTaskService::class)->skipOccurrence($record, $data['reason'] ?? null);

                        Notification::make()
                            ->title('Aufgabe übersprungen')
                            ->success()
                            ->send();
                    }),

                Action::make('toggleActive')
                    ->label(fn (RecurringTask $record) => $record->active ? 'Pausieren' : 'Fortsetzen')
                    ->icon(fn (RecurringTask $record) => $record->active ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->action(function (RecurringTask $record) {
                        $record->active ? $record->pause() : $record->resume();

                        Notification::make()
                            ->title($record->active ? 'Aufgabe fortgesetzt' : 'Aufgabe pausiert')
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('next_due_at', 'asc')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
