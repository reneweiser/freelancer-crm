<?php

namespace App\Filament\Resources\Reminders\Tables;

use App\Enums\ReminderPriority;
use App\Models\Reminder;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RemindersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Titel')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Reminder $record) => $record->remindable?->display_name ?? $record->remindable?->title ?? $record->remindable?->number),

                TextColumn::make('due_at')
                    ->label('Fällig')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->color(fn (Reminder $record) => $record->is_overdue ? 'danger' : null),

                TextColumn::make('priority')
                    ->label('Priorität')
                    ->badge(),

                TextColumn::make('recurrence')
                    ->label('Wiederholung')
                    ->placeholder('Einmalig'),

                IconColumn::make('is_system')
                    ->label('Typ')
                    ->boolean()
                    ->trueIcon('heroicon-o-cog-6-tooth')
                    ->falseIcon('heroicon-o-user')
                    ->trueColor('gray')
                    ->falseColor('primary')
                    ->tooltip(fn (Reminder $record) => $record->is_system ? 'Automatisch erstellt' : 'Manuell erstellt'),

                IconColumn::make('completed_at')
                    ->label('Erledigt')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Ausstehend',
                        'completed' => 'Erledigt',
                        'overdue' => 'Überfällig',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value']) {
                            'pending' => $query->pending(),
                            'completed' => $query->completed(),
                            'overdue' => $query->overdue(),
                            default => $query,
                        };
                    }),

                SelectFilter::make('priority')
                    ->label('Priorität')
                    ->options(ReminderPriority::class),
            ])
            ->recordActions([
                Action::make('complete')
                    ->label('Erledigt')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Reminder $record) => ! $record->completed_at)
                    ->action(fn (Reminder $record) => $record->complete()),

                Action::make('snooze')
                    ->label('Später')
                    ->icon('heroicon-o-clock')
                    ->visible(fn (Reminder $record) => ! $record->completed_at)
                    ->form([
                        Select::make('hours')
                            ->label('Erinnere mich in')
                            ->options([
                                1 => '1 Stunde',
                                4 => '4 Stunden',
                                24 => '1 Tag',
                                72 => '3 Tage',
                                168 => '1 Woche',
                            ])
                            ->default(24),
                    ])
                    ->action(fn (Reminder $record, array $data) => $record->snooze($data['hours'])),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('due_at', 'asc')
            ->modifyQueryUsing(fn (Builder $query) => $query->pending())
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
