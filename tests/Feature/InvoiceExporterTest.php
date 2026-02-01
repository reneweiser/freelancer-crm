<?php

use App\Filament\Exports\InvoiceExporter;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

it('defines all required tax-relevant columns', function () {
    $columns = InvoiceExporter::getColumns();
    $columnNames = collect($columns)->map(fn ($col) => $col->getName())->toArray();

    expect($columnNames)->toContain('number')
        ->toContain('status')
        ->toContain('client.display_name')
        ->toContain('issued_at')
        ->toContain('due_at')
        ->toContain('paid_at')
        ->toContain('payment_method')
        ->toContain('service_period_start')
        ->toContain('service_period_end')
        ->toContain('subtotal')
        ->toContain('vat_rate')
        ->toContain('vat_amount')
        ->toContain('total');
});

it('has German labels for all columns', function () {
    $columns = InvoiceExporter::getColumns();
    $labels = collect($columns)->map(fn ($col) => $col->getLabel())->toArray();

    expect($labels)->toContain('Rechnungsnummer')
        ->toContain('Status')
        ->toContain('Kunde')
        ->toContain('Rechnungsdatum')
        ->toContain('Fälligkeitsdatum')
        ->toContain('Bezahlt am')
        ->toContain('Zahlungsart')
        ->toContain('Leistungszeitraum Start')
        ->toContain('Leistungszeitraum Ende')
        ->toContain('Nettobetrag')
        ->toContain('MwSt.-Satz')
        ->toContain('MwSt.-Betrag')
        ->toContain('Bruttobetrag');
});

it('has exactly 13 columns for tax-relevant export', function () {
    $columns = InvoiceExporter::getColumns();

    expect($columns)->toHaveCount(13);
});

it('provides date range filter options', function () {
    $components = InvoiceExporter::getOptionsFormComponents();

    expect($components)->toHaveCount(2);

    $names = collect($components)->map(fn ($comp) => $comp->getName())->toArray();

    expect($names)->toContain('from')
        ->toContain('until');
});

it('returns German completion message', function () {
    $export = new Export;
    $export->successful_rows = 5;

    $message = InvoiceExporter::getCompletedNotificationBody($export);

    expect($message)->toBe('Der Export von 5 Rechnungen wurde abgeschlossen.');
});

it('formats large numbers correctly in notification', function () {
    $export = new Export;
    $export->successful_rows = 1234;

    $message = InvoiceExporter::getCompletedNotificationBody($export);

    expect($message)->toBe('Der Export von 1,234 Rechnungen wurde abgeschlossen.');
});

it('is configured for Invoice model', function () {
    expect(InvoiceExporter::getModel())->toBe(Invoice::class);
});

it('displays export action in table header', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['user_id' => $user->id]);

    Invoice::factory()->create([
        'user_id' => $user->id,
        'client_id' => $client->id,
    ]);

    $this->actingAs($user);

    Livewire::test(ListInvoices::class)
        ->assertOk()
        ->assertActionExists(TestAction::make('export')->table());
});

it('can see export action on table', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['user_id' => $user->id]);

    Invoice::factory()->create([
        'user_id' => $user->id,
        'client_id' => $client->id,
    ]);

    $this->actingAs($user);

    Livewire::test(ListInvoices::class)
        ->assertOk()
        ->assertActionVisible(TestAction::make('export')->table());
});
