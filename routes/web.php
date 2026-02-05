<?php

use App\Http\Controllers\PdfController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('/pdf/invoice/{invoice}/download', [PdfController::class, 'downloadInvoice'])
        ->name('pdf.invoice.download');
    Route::get('/pdf/invoice/{invoice}/stream', [PdfController::class, 'streamInvoice'])
        ->name('pdf.invoice.stream');
    Route::get('/pdf/offer/{project}/download', [PdfController::class, 'downloadOffer'])
        ->name('pdf.offer.download');
    Route::get('/pdf/offer/{project}/stream', [PdfController::class, 'streamOffer'])
        ->name('pdf.offer.stream');
});
