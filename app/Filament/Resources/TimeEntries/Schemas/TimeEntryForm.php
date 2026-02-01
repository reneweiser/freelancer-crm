<?php

namespace App\Filament\Resources\TimeEntries\Schemas;

use App\Enums\ProjectType;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TimeEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Projekt')
                    ->schema([
                        Select::make('project_id')
                            ->label('Projekt')
                            ->relationship(
                                'project',
                                'title',
                                fn ($query) => $query
                                    ->where('type', ProjectType::Hourly)
                                    ->whereNotIn('status', ['completed', 'cancelled', 'declined'])
                            )
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->title} ({$record->client->display_name})")
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),

                        Placeholder::make('hourly_rate_info')
                            ->label('Stundensatz')
                            ->content(function ($get) {
                                $projectId = $get('project_id');
                                if (! $projectId) {
                                    return '-';
                                }

                                $project = \App\Models\Project::find($projectId);

                                return $project?->hourly_rate
                                    ? number_format((float) $project->hourly_rate, 2, ',', '.').' EUR/Std.'
                                    : '-';
                            }),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Zeit')
                    ->schema([
                        DateTimePicker::make('started_at')
                            ->label('Beginn')
                            ->required()
                            ->default(now())
                            ->seconds(false)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, $set, $get) => self::calculateDuration($set, $get)),

                        DateTimePicker::make('ended_at')
                            ->label('Ende')
                            ->seconds(false)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, $set, $get) => self::calculateDuration($set, $get)),

                        TextInput::make('duration_minutes')
                            ->label('Dauer (Minuten)')
                            ->numeric()
                            ->minValue(1)
                            ->helperText('Wird automatisch berechnet, wenn Start- und Endzeit angegeben sind'),

                        Placeholder::make('duration_display')
                            ->label('Dauer')
                            ->content(function ($get) {
                                $minutes = (int) $get('duration_minutes');
                                if ($minutes <= 0) {
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
                            }),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Details')
                    ->schema([
                        Textarea::make('description')
                            ->label('Beschreibung')
                            ->rows(3)
                            ->placeholder('Was wurde gemacht?')
                            ->columnSpanFull(),

                        Checkbox::make('billable')
                            ->label('Abrechenbar')
                            ->default(true)
                            ->helperText('Nicht abrechenbare Zeiten werden bei der Rechnungserstellung nicht berücksichtigt'),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function calculateDuration($set, $get): void
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
}
