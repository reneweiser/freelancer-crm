<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->client = Client::factory()->create(['user_id' => $this->user->id]);
    Sanctum::actingAs($this->user);
});

describe('Invoice API - List', function () {
    it('lists invoices for authenticated user', function () {
        Invoice::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);
        Invoice::factory()->count(2)->create(); // Other user's invoices

        $response = $this->getJson('/api/v1/invoices');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');
    });

    it('filters invoices by status', function () {
        Invoice::factory()->draft()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);
        Invoice::factory()->paid()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->getJson('/api/v1/invoices?status=paid');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'paid');
    });

    it('filters invoices by year', function () {
        Invoice::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'issued_at' => '2026-01-15',
        ]);
        Invoice::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'issued_at' => '2025-01-15',
        ]);

        $response = $this->getJson('/api/v1/invoices?year=2026');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });
});

describe('Invoice API - Create', function () {
    it('creates an invoice with items', function () {
        $data = [
            'client_id' => $this->client->id,
            'issued_at' => now()->toDateString(),
            'due_at' => now()->addDays(14)->toDateString(),
            'vat_rate' => 19.00,
            'items' => [
                [
                    'description' => 'Website Development',
                    'quantity' => 1,
                    'unit' => 'pauschal',
                    'unit_price' => 5000.00,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/invoices', $data);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'draft');

        expect((float) $response->json('data.subtotal'))->toBe(5000.0)
            ->and((float) $response->json('data.vat_amount'))->toBe(950.0)
            ->and((float) $response->json('data.total'))->toBe(5950.0)
            ->and($response->json('data.number'))->toMatch('/^\d{4}-\d{3}$/');
    });

    it('requires at least one item', function () {
        $response = $this->postJson('/api/v1/invoices', [
            'client_id' => $this->client->id,
            'items' => [],
        ]);

        $response->assertUnprocessable();
    });

    it('uses user settings for defaults', function () {
        Setting::create([
            'user_id' => $this->user->id,
            'key' => 'default_vat_rate',
            'value' => '7.00',
        ]);
        Setting::create([
            'user_id' => $this->user->id,
            'key' => 'payment_terms_days',
            'value' => '30',
        ]);

        $response = $this->postJson('/api/v1/invoices', [
            'client_id' => $this->client->id,
            'items' => [
                ['description' => 'Test', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $response->assertCreated();

        expect((float) $response->json('data.vat_rate'))->toBe(7.0);

        $dueAt = now()->addDays(30)->toDateString();
        expect($response->json('data.due_at'))->toBe($dueAt);
    });
});

describe('Invoice API - Show', function () {
    it('shows invoice with computed fields', function () {
        $invoice = Invoice::factory()
            ->withTotals(1000)
            ->create([
                'user_id' => $this->user->id,
                'client_id' => $this->client->id,
            ]);

        $response = $this->getJson("/api/v1/invoices/{$invoice->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'formatted_total',
                    'allowed_transitions',
                    'client',
                    'items',
                ],
            ]);
    });
});

describe('Invoice API - Update', function () {
    it('updates a draft invoice', function () {
        $invoice = Invoice::factory()->draft()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->putJson("/api/v1/invoices/{$invoice->id}", [
            'notes' => 'Updated notes',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.notes', 'Updated notes');
    });

    it('rejects updating non-draft invoice', function () {
        $invoice = Invoice::factory()->sent()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->putJson("/api/v1/invoices/{$invoice->id}", [
            'notes' => 'Updated notes',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVOICE_NOT_DRAFT');
    });
});

describe('Invoice API - Delete', function () {
    it('deletes a draft invoice', function () {
        $invoice = Invoice::factory()->draft()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->deleteJson("/api/v1/invoices/{$invoice->id}");

        $response->assertOk()
            ->assertJsonPath('data.deleted', true);
    });

    it('rejects deleting non-draft invoice', function () {
        $invoice = Invoice::factory()->sent()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->deleteJson("/api/v1/invoices/{$invoice->id}");

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'CANNOT_DELETE_INVOICE');
    });
});

describe('Invoice API - From Project', function () {
    it('creates invoice from a project', function () {
        $project = Project::factory()
            ->hasItems(2)
            ->inProgress()
            ->create([
                'user_id' => $this->user->id,
                'client_id' => $this->client->id,
            ]);

        $response = $this->postJson('/api/v1/invoices/from-project', [
            'project_id' => $project->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.project_id', $project->id)
            ->assertJsonCount(2, 'data.items');
    });

    it('rejects draft projects', function () {
        $project = Project::factory()->draft()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->postJson('/api/v1/invoices/from-project', [
            'project_id' => $project->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'PROJECT_CANNOT_BE_INVOICED');
    });
});

describe('Invoice API - Mark Paid', function () {
    it('marks a sent invoice as paid', function () {
        $invoice = Invoice::factory()->sent()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->postJson("/api/v1/invoices/{$invoice->id}/mark-paid", [
            'payment_method' => 'Überweisung',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.payment_method', 'Überweisung');

        expect($invoice->fresh()->paid_at)->not->toBeNull();
    });

    it('rejects marking draft invoice as paid', function () {
        $invoice = Invoice::factory()->draft()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->postJson("/api/v1/invoices/{$invoice->id}/mark-paid");

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVOICE_NOT_SENT');
    });
});
