<?php

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectItem;
use App\Models\Setting;
use App\Models\User;
use App\Services\InvoiceCreationService;
use App\Services\SettingsService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->client = Client::factory()->create(['user_id' => $this->user->id]);

    Setting::create(['user_id' => $this->user->id, 'key' => 'default_payment_terms', 'value' => '14']);
    Setting::create(['user_id' => $this->user->id, 'key' => 'default_vat_rate', 'value' => '19']);

    $this->settings = new SettingsService($this->user);
    $this->service = new InvoiceCreationService($this->settings);
});

describe('Invoice creation from project', function () {
    it('creates an invoice from an in-progress project', function () {
        $project = Project::factory()->inProgress()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        ProjectItem::factory()->create([
            'project_id' => $project->id,
            'description' => 'Web Development',
            'quantity' => 10,
            'unit' => 'Stunden',
            'unit_price' => 100,
            'position' => 1,
        ]);

        $invoice = $this->service->createFromProject($project);

        expect($invoice)
            ->user_id->toBe($this->user->id)
            ->client_id->toBe($this->client->id)
            ->project_id->toBe($project->id)
            ->status->toBe(InvoiceStatus::Draft)
            ->vat_rate->toBe('19.00');
    });

    it('copies project items to invoice items', function () {
        $project = Project::factory()->inProgress()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        ProjectItem::factory()->create([
            'project_id' => $project->id,
            'description' => 'Web Development',
            'quantity' => 10,
            'unit' => 'Stunden',
            'unit_price' => 100,
            'position' => 1,
        ]);

        ProjectItem::factory()->create([
            'project_id' => $project->id,
            'description' => 'Hosting Setup',
            'quantity' => 1,
            'unit' => 'Pauschal',
            'unit_price' => 200,
            'position' => 2,
        ]);

        $invoice = $this->service->createFromProject($project);

        expect($invoice->items)->toHaveCount(2);
        expect($invoice->items->first()->description)->toBe('Web Development');
        expect($invoice->items->first()->quantity)->toBe('10.00');
        expect($invoice->items->first()->unit_price)->toBe('100.00');
    });

    it('calculates invoice totals correctly', function () {
        $project = Project::factory()->inProgress()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        ProjectItem::factory()->create([
            'project_id' => $project->id,
            'description' => 'Web Development',
            'quantity' => 10,
            'unit' => 'Stunden',
            'unit_price' => 100,
            'position' => 1,
        ]);

        $invoice = $this->service->createFromProject($project);

        expect($invoice->subtotal)->toBe('1000.00');
        expect($invoice->vat_amount)->toBe('190.00');
        expect($invoice->total)->toBe('1190.00');
    });

    it('sets service period from project dates', function () {
        $project = Project::factory()->inProgress()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
        ]);

        $invoice = $this->service->createFromProject($project);

        expect($invoice->service_period_start->toDateString())->toBe('2026-01-01');
        expect($invoice->service_period_end->toDateString())->toBe('2026-01-31');
    });

    it('generates sequential invoice number', function () {
        $project = Project::factory()->inProgress()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $invoice = $this->service->createFromProject($project);

        $year = now()->year;
        expect($invoice->number)->toBe("{$year}-001");
    });
});

describe('Invoice creation validation', function () {
    it('throws exception for draft project', function () {
        $project = Project::factory()->draft()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        expect(fn () => $this->service->createFromProject($project))
            ->toThrow(InvalidArgumentException::class);
    });

    it('throws exception for cancelled project', function () {
        $project = Project::factory()->cancelled()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        expect(fn () => $this->service->createFromProject($project))
            ->toThrow(InvalidArgumentException::class);
    });

    it('throws exception for declined project', function () {
        $project = Project::factory()->declined()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        expect(fn () => $this->service->createFromProject($project))
            ->toThrow(InvalidArgumentException::class);
    });
});

describe('Invoice creation from completed project', function () {
    it('can create invoice from completed project', function () {
        $project = Project::factory()->completed()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        ProjectItem::factory()->create([
            'project_id' => $project->id,
        ]);

        $invoice = $this->service->createFromProject($project);

        expect($invoice)->not->toBeNull();
        expect($invoice->status)->toBe(InvoiceStatus::Draft);
    });
});
