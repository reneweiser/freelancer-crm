<?php

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->client = Client::factory()->create(['user_id' => $this->user->id]);
});

it('marks sent invoices as overdue when past due date', function () {
    $invoice = Invoice::factory()->sent()->create([
        'user_id' => $this->user->id,
        'client_id' => $this->client->id,
        'due_at' => now()->subDays(5),
    ]);

    $this->artisan('invoices:check-overdue')
        ->expectsOutput('1 Rechnung(en) als überfällig markiert.')
        ->assertSuccessful();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Overdue);
});

it('does not mark sent invoices that are not past due date', function () {
    $invoice = Invoice::factory()->sent()->create([
        'user_id' => $this->user->id,
        'client_id' => $this->client->id,
        'due_at' => now()->addDays(5),
    ]);

    $this->artisan('invoices:check-overdue')
        ->expectsOutput('Keine überfälligen Rechnungen gefunden.')
        ->assertSuccessful();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
});

it('does not mark draft invoices as overdue', function () {
    $invoice = Invoice::factory()->draft()->create([
        'user_id' => $this->user->id,
        'client_id' => $this->client->id,
        'due_at' => now()->subDays(5),
    ]);

    $this->artisan('invoices:check-overdue')
        ->assertSuccessful();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Draft);
});

it('does not mark paid invoices as overdue', function () {
    $invoice = Invoice::factory()->paid()->create([
        'user_id' => $this->user->id,
        'client_id' => $this->client->id,
        'due_at' => now()->subDays(5),
    ]);

    $this->artisan('invoices:check-overdue')
        ->assertSuccessful();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

it('handles multiple overdue invoices', function () {
    // Create invoices one at a time to avoid number collision
    for ($i = 0; $i < 3; $i++) {
        Invoice::factory()->sent()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'due_at' => now()->subDays(5),
        ]);
    }

    $this->artisan('invoices:check-overdue')
        ->expectsOutput('3 Rechnung(en) als überfällig markiert.')
        ->assertSuccessful();

    expect(Invoice::where('status', InvoiceStatus::Overdue)->count())->toBe(3);
});

it('marks invoices as overdue when due date is today midnight', function () {
    Carbon::setTestNow(Carbon::create(2026, 1, 15, 10, 0, 0));

    $invoice = Invoice::factory()->sent()->create([
        'user_id' => $this->user->id,
        'client_id' => $this->client->id,
        'due_at' => Carbon::create(2026, 1, 14),
    ]);

    $this->artisan('invoices:check-overdue')
        ->assertSuccessful();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Overdue);

    Carbon::setTestNow();
});
