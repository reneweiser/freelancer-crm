<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Project;
use App\Models\ProjectItem;
use App\Models\Setting;
use App\Models\User;
use App\Services\PdfService;
use App\Services\SettingsService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->client = Client::factory()->create([
        'user_id' => $this->user->id,
        'company_name' => 'Test GmbH',
        'contact_name' => 'Max Mustermann',
        'street' => 'Teststraße 1',
        'postal_code' => '12345',
        'city' => 'Berlin',
    ]);

    Setting::create(['user_id' => $this->user->id, 'key' => 'business_name', 'value' => 'Mein Business']);
    Setting::create(['user_id' => $this->user->id, 'key' => 'business_street', 'value' => 'Musterstraße 1']);
    Setting::create(['user_id' => $this->user->id, 'key' => 'business_postal_code', 'value' => '10115']);
    Setting::create(['user_id' => $this->user->id, 'key' => 'business_city', 'value' => 'Berlin']);
    Setting::create(['user_id' => $this->user->id, 'key' => 'tax_number', 'value' => '123/456/78901']);
    Setting::create(['user_id' => $this->user->id, 'key' => 'iban', 'value' => 'DE89370400440532013000']);
    Setting::create(['user_id' => $this->user->id, 'key' => 'bic', 'value' => 'COBADEFFXXX']);
    Setting::create(['user_id' => $this->user->id, 'key' => 'bank_name', 'value' => 'Commerzbank']);

    $this->settings = new SettingsService($this->user);
    $this->pdfService = new PdfService($this->settings);
});

describe('Invoice PDF generation', function () {
    beforeEach(function () {
        $this->invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'number' => '2026-001',
            'issued_at' => '2026-01-15',
            'due_at' => '2026-01-29',
            'subtotal' => 1000.00,
            'vat_rate' => 19.00,
            'vat_amount' => 190.00,
            'total' => 1190.00,
            'service_period_start' => '2026-01-01',
            'service_period_end' => '2026-01-31',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $this->invoice->id,
            'description' => 'Web Development',
            'quantity' => 10,
            'unit' => 'Stunden',
            'unit_price' => 100.00,
            'vat_rate' => 19.00,
            'position' => 1,
        ]);
    });

    it('generates invoice PDF object', function () {
        $pdf = $this->pdfService->generateInvoicePdf($this->invoice);

        expect($pdf)->toBeInstanceOf(\Barryvdh\DomPDF\PDF::class);
    });

    it('includes invoice number in PDF content', function () {
        $pdf = $this->pdfService->generateInvoicePdf($this->invoice);
        $output = $pdf->output();

        expect($output)->toBeString();
        expect(strlen($output))->toBeGreaterThan(0);
    });

    it('returns proper filename for download', function () {
        $response = $this->pdfService->downloadInvoicePdf($this->invoice);

        expect($response->headers->get('content-disposition'))
            ->toContain('Rechnung-2026-001.pdf');
    });
});

describe('Offer PDF generation', function () {
    beforeEach(function () {
        $this->project = Project::factory()->sent()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'title' => 'Website Redesign',
            'reference' => 'PROJ-001',
            'offer_date' => '2026-01-10',
            'offer_valid_until' => '2026-02-10',
        ]);

        ProjectItem::factory()->create([
            'project_id' => $this->project->id,
            'description' => 'Design und Entwicklung',
            'quantity' => 1,
            'unit' => 'Pauschal',
            'unit_price' => 5000.00,
            'position' => 1,
        ]);
    });

    it('generates offer PDF object', function () {
        $pdf = $this->pdfService->generateOfferPdf($this->project);

        expect($pdf)->toBeInstanceOf(\Barryvdh\DomPDF\PDF::class);
    });

    it('includes project reference in PDF content', function () {
        $pdf = $this->pdfService->generateOfferPdf($this->project);
        $output = $pdf->output();

        expect($output)->toBeString();
        expect(strlen($output))->toBeGreaterThan(0);
    });

    it('returns proper filename for download', function () {
        $response = $this->pdfService->downloadOfferPdf($this->project);

        expect($response->headers->get('content-disposition'))
            ->toContain('Angebot-PROJ-001.pdf');
    });
});

describe('PDF controller routes', function () {
    it('requires authentication for invoice PDF download', function () {
        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->get(route('pdf.invoice.download', $invoice));

        $response->assertRedirectToRoute('filament.admin.auth.login');
    });

    it('allows authenticated user to download own invoice PDF', function () {
        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);

        $response = $this->actingAs($this->user)
            ->get(route('pdf.invoice.download', $invoice));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    });

    it('forbids access to other user invoice PDF', function () {
        $otherUser = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'user_id' => $otherUser->id,
            'client_id' => Client::factory()->create(['user_id' => $otherUser->id])->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('pdf.invoice.download', $invoice));

        $response->assertForbidden();
    });

    it('requires authentication for offer PDF download', function () {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->get(route('pdf.offer.download', $project));

        $response->assertRedirectToRoute('filament.admin.auth.login');
    });

    it('allows authenticated user to download own offer PDF', function () {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        ProjectItem::factory()->create(['project_id' => $project->id]);

        $response = $this->actingAs($this->user)
            ->get(route('pdf.offer.download', $project));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    });

    it('forbids access to other user offer PDF', function () {
        $otherUser = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $otherUser->id,
            'client_id' => Client::factory()->create(['user_id' => $otherUser->id])->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('pdf.offer.download', $project));

        $response->assertForbidden();
    });
});
