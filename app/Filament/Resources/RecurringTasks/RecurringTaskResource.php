<?php

namespace App\Filament\Resources\RecurringTasks;

use App\Filament\Resources\RecurringTasks\Pages\CreateRecurringTask;
use App\Filament\Resources\RecurringTasks\Pages\EditRecurringTask;
use App\Filament\Resources\RecurringTasks\Pages\ListRecurringTasks;
use App\Filament\Resources\RecurringTasks\RelationManagers\LogsRelationManager;
use App\Filament\Resources\RecurringTasks\Schemas\RecurringTaskForm;
use App\Filament\Resources\RecurringTasks\Tables\RecurringTasksTable;
use App\Models\RecurringTask;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RecurringTaskResource extends Resource
{
    protected static ?string $model = RecurringTask::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static ?string $navigationLabel = 'Wiederkehrende Aufgaben';

    protected static ?string $modelLabel = 'Wiederkehrende Aufgabe';

    protected static ?string $pluralModelLabel = 'Wiederkehrende Aufgaben';

    protected static ?int $navigationSort = 6;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()->overdue()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return RecurringTaskForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecurringTasksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            LogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecurringTasks::route('/'),
            'create' => CreateRecurringTask::route('/create'),
            'edit' => EditRecurringTask::route('/{record}/edit'),
        ];
    }
}
