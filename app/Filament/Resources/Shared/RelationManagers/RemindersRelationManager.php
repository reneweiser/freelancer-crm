<?php

namespace App\Filament\Resources\Shared\RelationManagers;

use App\Enums\ReminderPriority;
use App\Models\Reminder;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RemindersRelationManager extends RelationManager
{
    protected static string $relationship = 'reminders';

    protected static ?string $title = 'Erinnerungen';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label('Titel')
                ->required()
                ->maxLength(255),

            DateTimePicker::make('due_at')
                ->label('Fällig am')
                ->required()
                ->native(false)
                ->displayFormat('d.m.Y H:i'),

            Textarea::make('description')
                ->label('Beschreibung')
                ->rows(3),

            Select::make('priority')
                ->label('Priorität')
                ->options(ReminderPriority::class)
                ->default(ReminderPriority::Normal),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Titel')
                    ->searchable(),

                TextColumn::make('due_at')
                    ->label('Fällig')
                    ->dateTime('d.m.Y H:i')
                    ->color(fn (Reminder $record) => $record->is_overdue ? 'danger' : null)
                    ->sortable(),

                TextColumn::make('priority')
                    ->label('Priorität')
                    ->badge(),

                IconColumn::make('completed_at')
                    ->label('Erledigt')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock'),
            ])
            ->defaultSort('due_at', 'asc')
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();

                        return $data;
                    }),
            ])
            ->recordActions([
                Action::make('complete')
                    ->label('Erledigt')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Reminder $record) => ! $record->completed_at)
                    ->action(fn (Reminder $record) => $record->complete()),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->pending())
            ->emptyStateHeading('Keine Erinnerungen')
            ->emptyStateDescription('Erstellen Sie eine Erinnerung für diesen Eintrag.');
    }
}
