<?php

namespace App\Filament\Resources\RecurringTasks\Pages;

use App\Filament\Resources\RecurringTasks\RecurringTaskResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRecurringTasks extends ListRecords
{
    protected static string $resource = RecurringTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
