<?php

namespace App\Filament\Resources\RecurringTasks\Pages;

use App\Filament\Resources\RecurringTasks\RecurringTaskResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRecurringTask extends EditRecord
{
    protected static string $resource = RecurringTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
