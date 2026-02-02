<?php

use App\Services\RecurringTaskService;
use App\Services\ReminderService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('invoices:check-overdue')->dailyAt('08:00');

// Process due recurring tasks daily at 8am
Schedule::call(function () {
    $service = app(RecurringTaskService::class);
    $processed = $service->processDueTasks();

    Log::info("Processed {$processed} recurring tasks");
})->daily()->at('08:00')->name('recurring-tasks:process');

// Create upcoming reminders for recurring tasks daily at 7am
Schedule::call(function () {
    $service = app(RecurringTaskService::class);
    $created = $service->createUpcomingReminders();

    Log::info("Created {$created} upcoming task reminders");
})->daily()->at('07:00')->name('recurring-tasks:reminders');

// Check for due reminders every minute and send notifications
Schedule::call(function () {
    $service = app(ReminderService::class);
    $service->processDueReminders();
})->everyMinute()->name('reminders:notify');
