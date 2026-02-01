<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Project;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class PdfService
{
    public function __construct(
        protected SettingsService $settings
    ) {}

    public function generateInvoicePdf(Invoice $invoice): \Barryvdh\DomPDF\PDF
    {
        $invoice->load(['client', 'items', 'project']);

        $data = [
            'invoice' => $invoice,
            'business' => $this->getBusinessData(),
        ];

        return Pdf::loadView('pdf.invoice', $data)
            ->setPaper('a4')
            ->setOption('dpi', 150)
            ->setOption('defaultFont', 'DejaVu Sans');
    }

    public function downloadInvoicePdf(Invoice $invoice): Response
    {
        $pdf = $this->generateInvoicePdf($invoice);
        $filename = $this->getInvoiceFilename($invoice);

        return $pdf->download($filename);
    }

    public function streamInvoicePdf(Invoice $invoice): Response
    {
        $pdf = $this->generateInvoicePdf($invoice);
        $filename = $this->getInvoiceFilename($invoice);

        return $pdf->stream($filename);
    }

    public function generateOfferPdf(Project $project): \Barryvdh\DomPDF\PDF
    {
        $project->load(['client', 'items']);

        $data = [
            'project' => $project,
            'business' => $this->getBusinessData(),
            'vatRate' => (float) $this->settings->get('default_vat_rate', 19.00),
        ];

        return Pdf::loadView('pdf.offer', $data)
            ->setPaper('a4')
            ->setOption('dpi', 150)
            ->setOption('defaultFont', 'DejaVu Sans');
    }

    public function downloadOfferPdf(Project $project): Response
    {
        $pdf = $this->generateOfferPdf($project);
        $filename = $this->getOfferFilename($project);

        return $pdf->download($filename);
    }

    public function streamOfferPdf(Project $project): Response
    {
        $pdf = $this->generateOfferPdf($project);
        $filename = $this->getOfferFilename($project);

        return $pdf->stream($filename);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getBusinessData(): array
    {
        return [
            'name' => $this->settings->get('business_name', ''),
            'street' => $this->settings->get('business_street', ''),
            'postal_code' => $this->settings->get('business_postal_code', ''),
            'city' => $this->settings->get('business_city', ''),
            'tax_number' => $this->settings->get('tax_number', ''),
            'vat_id' => $this->settings->get('vat_id', ''),
            'bank_name' => $this->settings->get('bank_name', ''),
            'iban' => $this->settings->get('iban', ''),
            'bic' => $this->settings->get('bic', ''),
            'email' => $this->settings->get('business_email', ''),
            'phone' => $this->settings->get('business_phone', ''),
            'website' => $this->settings->get('business_website', ''),
        ];
    }

    protected function getInvoiceFilename(Invoice $invoice): string
    {
        return "Rechnung-{$invoice->number}.pdf";
    }

    protected function getOfferFilename(Project $project): string
    {
        $reference = $project->reference ?: $project->id;

        return "Angebot-{$reference}.pdf";
    }
}
