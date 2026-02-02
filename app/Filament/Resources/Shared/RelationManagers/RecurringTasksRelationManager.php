<?php

namespace App\Filament\Resources\Shared\RelationManagers;

use App\Enums\TaskFrequency;
use App\Models\RecurringTask;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RecurringTasksRelationManager extends RelationManager
{
    protected static string $relationship = 'recurringTasks';

    protected static ?string $title = 'Wiederkehrende Aufgaben';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label('Titel')
                ->required()
                ->maxLength(255),

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

            TextInput::make('amount')
                ->label('Betrag')
                ->numeric()
                ->prefix('€'),

            Toggle::make('active')
                ->label('Aktiv')
                ->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Aufgabe')
                    ->searchable(),

                TextColumn::make('frequency')
                    ->label('Frequenz')
                    ->badge(),

                TextColumn::make('next_due_at')
                    ->label('Nächste Fälligkeit')
                    ->date('d.m.Y')
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
            ->defaultSort('next_due_at', 'asc')
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Keine wiederkehrenden Aufgaben')
            ->emptyStateDescription('Erstellen Sie eine wiederkehrende Aufgabe für diesen Kunden.');
    }
}
