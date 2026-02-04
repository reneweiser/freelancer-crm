<?php

use App\Http\Controllers\Api\V1\AiController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ReminderController;
use App\Http\Controllers\Api\V1\StatsController;
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

    // AI Helper Endpoints
    Route::get('stats', [StatsController::class, 'index'])->name('stats.index');
    Route::post('batch', [AiController::class, 'batch'])->name('ai.batch');
    Route::post('validate', [AiController::class, 'validate'])->name('ai.validate');
});
