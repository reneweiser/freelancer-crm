<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Reminders\ReminderResource;
use App\Models\Reminder;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class UpcomingRemindersWidget extends Widget
{
    protected static ?int $sort = 2;

    protected string $view = 'filament.widgets.upcoming-reminders-widget';

    protected int|string|array $columnSpan = 'full';

    public function getReminders(): Collection
    {
        return Reminder::query()
            ->upcoming(7)
            ->with('remindable')
            ->limit(5)
            ->get();
    }

    public function completeReminder(int $id): void
    {
        $reminder = Reminder::findOrFail($id);
        $reminder->complete();

        Notification::make()
            ->title('Erinnerung erledigt')
            ->success()
            ->send();
    }

    public function snoozeReminder(int $id, int $hours = 24): void
    {
        $reminder = Reminder::findOrFail($id);
        $reminder->snooze($hours);

        Notification::make()
            ->title('Erinnerung verschoben')
            ->success()
            ->send();
    }

    public function hasReminders(): bool
    {
        return $this->getReminders()->isNotEmpty();
    }

    public function getCreateUrl(): string
    {
        return ReminderResource::getUrl('create');
    }

    public function getAllRemindersUrl(): string
    {
        return ReminderResource::getUrl();
    }
}
