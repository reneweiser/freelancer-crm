<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\ProjectType;
use App\Models\TimeEntry;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TimeEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'timeEntries';

    protected static ?string $title = 'Zeiterfassung';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->type === ProjectType::Hourly;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        DateTimePicker::make('started_at')
                            ->label('Beginn')
                            ->required()
                            ->default(now())
                            ->seconds(false)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, $set, $get) => $this->calculateDuration($set, $get)),

                        DateTimePicker::make('ended_at')
                            ->label('Ende')
                            ->seconds(false)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, $set, $get) => $this->calculateDuration($set, $get)),

                        TextInput::make('duration_minutes')
                            ->label('Dauer (Minuten)')
                            ->numeric()
                            ->minValue(1)
                            ->helperText('Wird automatisch berechnet'),

                        Checkbox::make('billable')
                            ->label('Abrechenbar')
                            ->default(true),

                        Textarea::make('description')
                            ->label('Beschreibung')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('started_at')
                    ->label('Datum')
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('started_at')
                    ->label('Zeit')
                    ->formatStateUsing(function (TimeEntry $record) {
                        $start = $record->started_at->format('H:i');
                        $end = $record->ended_at?->format('H:i') ?? '-';

                        return "{$start} - {$end}";
                    }),

                TextColumn::make('description')
                    ->label('Beschreibung')
                    ->limit(50)
                    ->searchable(),

                TextColumn::make('duration_minutes')
                    ->label('Dauer')
                    ->formatStateUsing(fn ($state) => $this->formatDuration($state))
                    ->summarize(Sum::make()
                        ->formatStateUsing(fn ($state) => $this->formatDuration($state))
                        ->label('Gesamt')),

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
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (TimeEntry $record) => $record->invoice_id === null),
                DeleteAction::make()
                    ->visible(fn (TimeEntry $record) => $record->invoice_id === null),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
            ->emptyStateHeading('Keine Zeiteinträge')
            ->emptyStateDescription('Erfassen Sie Ihre Arbeitszeit für dieses Projekt.');
    }

    protected function calculateDuration($set, $get): void
    {
        $startedAt = $get('started_at');
        $endedAt = $get('ended_at');

        if ($startedAt && $endedAt) {
            $start = \Carbon\Carbon::parse($startedAt);
            $end = \Carbon\Carbon::parse($endedAt);

            if ($end->gt($start)) {
                $set('duration_minutes', $start->diffInMinutes($end));
            }
        }
    }

    protected function formatDuration(?int $minutes): string
    {
        if ($minutes === null || $minutes <= 0) {
            return '-';
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        if ($hours > 0 && $mins > 0) {
            return "{$hours} Std. {$mins} Min.";
        }

        if ($hours > 0) {
            return "{$hours} Std.";
        }

        return "{$mins} Min.";
    }
}
