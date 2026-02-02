<?php

namespace App\Filament\Resources\RecurringTasks\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LogsRelationManager extends RelationManager
{
    protected static string $relationship = 'logs';

    protected static ?string $title = 'Verlauf';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('due_date')
                    ->label('Fälligkeitsdatum')
                    ->date('d.m.Y'),

                TextColumn::make('action')
                    ->label('Aktion')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'reminder_created' => 'Erinnerung erstellt',
                        'manually_completed' => 'Manuell erledigt',
                        'skipped' => 'Übersprungen',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'reminder_created' => 'success',
                        'skipped' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('notes')
                    ->label('Notizen')
                    ->placeholder('-')
                    ->limit(50),

                TextColumn::make('created_at')
                    ->label('Datum')
                    ->dateTime('d.m.Y H:i'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
