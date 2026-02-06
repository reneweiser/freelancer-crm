<?php

use App\Http\Controllers\Api\V1\AiController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\RecurringTaskController;
use App\Http\Controllers\Api\V1\ReminderController;
use App\Http\Controllers\Api\V1\StatsController;
use App\Http\Controllers\Api\V1\TimeEntryController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {
    // Clients
    Route::apiResource('clients', ClientController::class);

    // Projects
    Route::apiResource('projects', ProjectController::class);
    Route::post('projects/{project}/transition', [ProjectController::class, 'transition'])
        ->name('projects.transition');

    // Invoices
    Route::apiResource('invoices', InvoiceController::class);
    Route::post('invoices/from-project', [InvoiceController::class, 'fromProject'])
        ->name('invoices.from-project');
    Route::post('invoices/{invoice}/mark-paid', [InvoiceController::class, 'markPaid'])
        ->name('invoices.mark-paid');

    // Reminders
    Route::apiResource('reminders', ReminderController::class);
    Route::post('reminders/{reminder}/complete', [ReminderController::class, 'complete'])
        ->name('reminders.complete');
    Route::post('reminders/{reminder}/snooze', [ReminderController::class, 'snooze'])
        ->name('reminders.snooze');

    // Time Entries
    Route::apiResource('time-entries', TimeEntryController::class);
    Route::post('time-entries/start', [TimeEntryController::class, 'start'])
        ->name('time-entries.start');
    Route::post('time-entries/{timeEntry}/stop', [TimeEntryController::class, 'stop'])
        ->name('time-entries.stop');

    // Recurring Tasks
    Route::apiResource('recurring-tasks', RecurringTaskController::class);
    Route::post('recurring-tasks/{recurringTask}/pause', [RecurringTaskController::class, 'pause'])
        ->name('recurring-tasks.pause');
    Route::post('recurring-tasks/{recurringTask}/resume', [RecurringTaskController::class, 'resume'])
        ->name('recurring-tasks.resume');
    Route::post('recurring-tasks/{recurringTask}/skip', [RecurringTaskController::class, 'skip'])
        ->name('recurring-tasks.skip');
    Route::post('recurring-tasks/{recurringTask}/advance', [RecurringTaskController::class, 'advance'])
        ->name('recurring-tasks.advance');

    // AI Helper Endpoints
    Route::get('stats', [StatsController::class, 'index'])->name('stats.index');
    Route::post('batch', [AiController::class, 'batch'])->name('ai.batch');
    Route::post('validate', [AiController::class, 'validate'])->name('ai.validate');
});
