<?php

namespace App\Filament\Resources\RecurringTasks\Pages;

use App\Filament\Resources\RecurringTasks\RecurringTaskResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRecurringTask extends CreateRecord
{
    protected static string $resource = RecurringTaskResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }
}
