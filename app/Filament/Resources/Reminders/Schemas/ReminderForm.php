<?php

namespace App\Filament\Resources\Reminders\Schemas;

use App\Enums\ReminderPriority;
use App\Enums\ReminderRecurrence;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReminderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('title')
                            ->label('Titel')
                            ->required()
                            ->maxLength(255),

                        Textarea::make('description')
                            ->label('Beschreibung')
                            ->rows(3),

                        DateTimePicker::make('due_at')
                            ->label('Fällig am')
                            ->required()
                            ->native(false)
                            ->displayFormat('d.m.Y H:i'),

                        Select::make('priority')
                            ->label('Priorität')
                            ->options(ReminderPriority::class)
                            ->default(ReminderPriority::Normal),

                        Select::make('recurrence')
                            ->label('Wiederholung')
                            ->options(ReminderRecurrence::class)
                            ->placeholder('Keine Wiederholung'),

                        MorphToSelect::make('remindable')
                            ->label('Verknüpft mit')
                            ->types([
                                MorphToSelect\Type::make(Client::class)
                                    ->titleAttribute('contact_name')
                                    ->getOptionLabelFromRecordUsing(fn (Client $record): string => $record->display_name)
                                    ->label('Kunde'),
                                MorphToSelect\Type::make(Project::class)
                                    ->titleAttribute('title')
                                    ->label('Projekt'),
                                MorphToSelect\Type::make(Invoice::class)
                                    ->titleAttribute('number')
                                    ->label('Rechnung'),
                            ])
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(2),
            ]);
    }
}
