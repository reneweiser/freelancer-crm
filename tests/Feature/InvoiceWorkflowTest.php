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

describe('Invoice status transitions', function () {
    it('can transition from draft to sent', function () {
        $invoice = Invoice::factory()->draft()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $invoice->update(['status' => InvoiceStatus::Sent]);

        expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
    });

    it('can mark sent invoice as paid', function () {
        $invoice = Invoice::factory()->sent()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        Carbon::setTestNow(now());
        $invoice->markAsPaid(['payment_method' => 'Überweisung']);

        expect($invoice->fresh())
            ->status->toBe(InvoiceStatus::Paid)
            ->paid_at->toDateString()->toBe(now()->toDateString())
            ->payment_method->toBe('Überweisung');
    });

    it('can mark overdue invoice as paid', function () {
        $invoice = Invoice::factory()->overdue()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $invoice->markAsPaid();

        expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
    });

    it('can cancel a draft invoice', function () {
        $invoice = Invoice::factory()->draft()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $invoice->update(['status' => InvoiceStatus::Cancelled]);

        expect($invoice->fresh()->status)->toBe(InvoiceStatus::Cancelled);
    });

    it('can cancel a sent invoice', function () {
        $invoice = Invoice::factory()->sent()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $invoice->update(['status' => InvoiceStatus::Cancelled]);

        expect($invoice->fresh()->status)->toBe(InvoiceStatus::Cancelled);
    });
});

describe('Invoice status helper methods', function () {
    it('identifies terminal statuses correctly', function () {
        expect(InvoiceStatus::Paid->isTerminal())->toBeTrue();
        expect(InvoiceStatus::Cancelled->isTerminal())->toBeTrue();
        expect(InvoiceStatus::Draft->isTerminal())->toBeFalse();
        expect(InvoiceStatus::Sent->isTerminal())->toBeFalse();
        expect(InvoiceStatus::Overdue->isTerminal())->toBeFalse();
    });

    it('identifies unpaid statuses correctly', function () {
        expect(InvoiceStatus::Sent->isUnpaid())->toBeTrue();
        expect(InvoiceStatus::Overdue->isUnpaid())->toBeTrue();
        expect(InvoiceStatus::Draft->isUnpaid())->toBeFalse();
        expect(InvoiceStatus::Paid->isUnpaid())->toBeFalse();
        expect(InvoiceStatus::Cancelled->isUnpaid())->toBeFalse();
    });
});

describe('Invoice number generation', function () {
    it('generates sequential invoice numbers', function () {
        $invoice1 = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $invoice2 = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $year = now()->year;

        expect($invoice1->number)->toBe("{$year}-001");
        expect($invoice2->number)->toBe("{$year}-002");
    });

    it('formats invoice numbers with year prefix', function () {
        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $year = now()->year;
        expect($invoice->number)->toStartWith("{$year}-");
    });
});

describe('Invoice allowed transitions', function () {
    it('allows draft to sent transition', function () {
        expect(InvoiceStatus::Draft->canTransitionTo(InvoiceStatus::Sent))->toBeTrue();
    });

    it('allows draft to cancelled transition', function () {
        expect(InvoiceStatus::Draft->canTransitionTo(InvoiceStatus::Cancelled))->toBeTrue();
    });

    it('allows sent to paid transition', function () {
        expect(InvoiceStatus::Sent->canTransitionTo(InvoiceStatus::Paid))->toBeTrue();
    });

    it('allows sent to overdue transition', function () {
        expect(InvoiceStatus::Sent->canTransitionTo(InvoiceStatus::Overdue))->toBeTrue();
    });

    it('allows overdue to paid transition', function () {
        expect(InvoiceStatus::Overdue->canTransitionTo(InvoiceStatus::Paid))->toBeTrue();
    });

    it('does not allow paid to any transition', function () {
        expect(InvoiceStatus::Paid->allowedTransitions())->toBeEmpty();
    });

    it('does not allow cancelled to any transition', function () {
        expect(InvoiceStatus::Cancelled->allowedTransitions())->toBeEmpty();
    });
});
