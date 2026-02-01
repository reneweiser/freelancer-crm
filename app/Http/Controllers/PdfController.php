<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Project;
use App\Services\PdfService;
use App\Services\SettingsService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PdfController extends Controller
{
    public function downloadInvoice(Invoice $invoice): Response|SymfonyResponse
    {
        if ($invoice->user_id !== auth()->id()) {
            abort(403);
        }

        $settingsService = new SettingsService(auth()->user());
        $pdfService = new PdfService($settingsService);

        return $pdfService->downloadInvoicePdf($invoice);
    }

    public function streamInvoice(Invoice $invoice): Response|SymfonyResponse
    {
        if ($invoice->user_id !== auth()->id()) {
            abort(403);
        }

        $settingsService = new SettingsService(auth()->user());
        $pdfService = new PdfService($settingsService);

        return $pdfService->streamInvoicePdf($invoice);
    }

    public function downloadOffer(Project $project): Response|SymfonyResponse
    {
        if ($project->user_id !== auth()->id()) {
            abort(403);
        }

        $settingsService = new SettingsService(auth()->user());
        $pdfService = new PdfService($settingsService);

        return $pdfService->downloadOfferPdf($project);
    }

    public function streamOffer(Project $project): Response|SymfonyResponse
    {
        if ($project->user_id !== auth()->id()) {
            abort(403);
        }

        $settingsService = new SettingsService(auth()->user());
        $pdfService = new PdfService($settingsService);

        return $pdfService->streamOfferPdf($project);
    }
}
