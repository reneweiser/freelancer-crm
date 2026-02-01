<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Setting;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\InvoiceCreationService;
use App\Services\SettingsService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->client = Client::factory()->create(['user_id' => $this->user->id]);

    Setting::create(['user_id' => $this->user->id, 'key' => 'default_payment_terms', 'value' => '14']);
    Setting::create(['user_id' => $this->user->id, 'key' => 'default_vat_rate', 'value' => '19']);
});

describe('TimeEntry model', function () {
    it('belongs to a user', function () {
        $entry = TimeEntry::factory()->create(['user_id' => $this->user->id]);

        expect($entry->user)->toBeInstanceOf(User::class);
        expect($entry->user->id)->toBe($this->user->id);
    });

    it('belongs to a project', function () {
        $project = Project::factory()->hourly()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $entry = TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $project->id,
        ]);

        expect($entry->project)->toBeInstanceOf(Project::class);
        expect($entry->project->id)->toBe($project->id);
    });

    it('calculates duration from start and end times on save', function () {
        $project = Project::factory()->hourly()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $entry = TimeEntry::create([
            'user_id' => $this->user->id,
            'project_id' => $project->id,
            'started_at' => now()->setTime(9, 0, 0),
            'ended_at' => now()->setTime(11, 30, 0),
            'billable' => true,
        ]);

        expect($entry->duration_minutes)->toBe(150);
    });

    it('formats duration correctly', function () {
        $project = Project::factory()->hourly()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $entry = TimeEntry::factory()->withDuration(150)->create([
            'user_id' => $this->user->id,
            'project_id' => $project->id,
        ]);

        expect($entry->formatted_duration)->toBe('2 Std. 30 Min.');
    });

    it('calculates duration in hours', function () {
        $project = Project::factory()->hourly()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $entry = TimeEntry::factory()->withDuration(90)->create([
            'user_id' => $this->user->id,
            'project_id' => $project->id,
        ]);

        expect($entry->duration_hours)->toBe(1.5);
    });
});

describe('TimeEntry scopes', function () {
    beforeEach(function () {
        $this->project = Project::factory()->hourly()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);
    });

    it('filters billable entries', function () {
        TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'billable' => true,
        ]);
        TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'billable' => false,
        ]);

        expect(TimeEntry::billable()->count())->toBe(1);
        expect(TimeEntry::nonBillable()->count())->toBe(1);
    });

    it('filters unbilled entries', function () {
        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'invoice_id' => null,
        ]);
        TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'invoice_id' => $invoice->id,
        ]);

        expect(TimeEntry::unbilled()->count())->toBe(1);
        expect(TimeEntry::invoiced()->count())->toBe(1);
    });
});

describe('Project time tracking', function () {
    it('calculates total hours for a project', function () {
        $project = Project::factory()->hourly()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        TimeEntry::factory()->withDuration(60)->create([
            'user_id' => $this->user->id,
            'project_id' => $project->id,
        ]);
        TimeEntry::factory()->withDuration(90)->create([
            'user_id' => $this->user->id,
            'project_id' => $project->id,
        ]);

        expect($project->fresh()->total_hours)->toBe(2.5);
    });

    it('calculates billable hours for a project', function () {
        $project = Project::factory()->hourly()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        TimeEntry::factory()->withDuration(60)->create([
            'user_id' => $this->user->id,
            'project_id' => $project->id,
            'billable' => true,
        ]);
        TimeEntry::factory()->withDuration(30)->create([
            'user_id' => $this->user->id,
            'project_id' => $project->id,
            'billable' => false,
        ]);

        expect($project->fresh()->billable_hours)->toBe(1.0);
    });

    it('calculates unbilled hours for a project', function () {
        $project = Project::factory()->hourly()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        TimeEntry::factory()->withDuration(60)->create([
            'user_id' => $this->user->id,
            'project_id' => $project->id,
            'billable' => true,
            'invoice_id' => null,
        ]);
        TimeEntry::factory()->withDuration(60)->create([
            'user_id' => $this->user->id,
            'project_id' => $project->id,
            'billable' => true,
            'invoice_id' => $invoice->id,
        ]);

        expect($project->fresh()->unbilled_hours)->toBe(1.0);
    });

    it('calculates unbilled amount based on hourly rate', function () {
        $project = Project::factory()->hourly()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'hourly_rate' => 100.00,
        ]);

        TimeEntry::factory()->withDuration(90)->create([
            'user_id' => $this->user->id,
            'project_id' => $project->id,
            'billable' => true,
            'invoice_id' => null,
        ]);

        expect($project->fresh()->unbilled_amount)->toBe(150.0);
    });
});

describe('Invoice creation with time entries', function () {
    it('adds time entries as line item when creating invoice from hourly project', function () {
        $project = Project::factory()->hourly()->inProgress()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'hourly_rate' => 100.00,
            'start_date' => '2026-01-15',
        ]);

        TimeEntry::create([
            'user_id' => $this->user->id,
            'project_id' => $project->id,
            'started_at' => '2026-01-15 09:00:00',
            'ended_at' => '2026-01-15 11:00:00',
            'billable' => true,
        ]);

        TimeEntry::create([
            'user_id' => $this->user->id,
            'project_id' => $project->id,
            'started_at' => '2026-01-16 14:00:00',
            'ended_at' => '2026-01-16 15:00:00',
            'billable' => true,
        ]);

        $settings = new SettingsService($this->user);
        $service = new InvoiceCreationService($settings);

        $invoice = $service->createFromProject($project);

        expect($invoice->items)->toHaveCount(1);
        expect($invoice->items->first()->description)->toContain('Arbeitszeit');
        expect($invoice->items->first()->description)->toContain('15.01.2026 - 16.01.2026');
        expect($invoice->items->first()->quantity)->toBe('3.00');
        expect($invoice->items->first()->unit)->toBe('Stunden');
        expect($invoice->items->first()->unit_price)->toBe('100.00');
    });

    it('marks time entries as invoiced after creating invoice', function () {
        $project = Project::factory()->hourly()->inProgress()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'hourly_rate' => 100.00,
        ]);

        $entry1 = TimeEntry::factory()->withDuration(60)->create([
            'user_id' => $this->user->id,
            'project_id' => $project->id,
            'billable' => true,
        ]);

        $entry2 = TimeEntry::factory()->withDuration(60)->create([
            'user_id' => $this->user->id,
            'project_id' => $project->id,
            'billable' => true,
        ]);

        $settings = new SettingsService($this->user);
        $service = new InvoiceCreationService($settings);

        $invoice = $service->createFromProject($project);

        expect($entry1->fresh()->invoice_id)->toBe($invoice->id);
        expect($entry2->fresh()->invoice_id)->toBe($invoice->id);
    });

    it('does not include non-billable time entries in invoice', function () {
        $project = Project::factory()->hourly()->inProgress()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'hourly_rate' => 100.00,
        ]);

        TimeEntry::factory()->withDuration(60)->create([
            'user_id' => $this->user->id,
            'project_id' => $project->id,
            'billable' => true,
        ]);

        TimeEntry::factory()->withDuration(60)->create([
            'user_id' => $this->user->id,
            'project_id' => $project->id,
            'billable' => false,
        ]);

        $settings = new SettingsService($this->user);
        $service = new InvoiceCreationService($settings);

        $invoice = $service->createFromProject($project);

        expect($invoice->items)->toHaveCount(1);
        expect($invoice->items->first()->quantity)->toBe('1.00');
    });

    it('does not include already invoiced time entries', function () {
        $project = Project::factory()->hourly()->inProgress()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'hourly_rate' => 100.00,
        ]);

        $existingInvoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        TimeEntry::factory()->withDuration(60)->create([
            'user_id' => $this->user->id,
            'project_id' => $project->id,
            'billable' => true,
            'invoice_id' => $existingInvoice->id,
        ]);

        TimeEntry::factory()->withDuration(60)->create([
            'user_id' => $this->user->id,
            'project_id' => $project->id,
            'billable' => true,
            'invoice_id' => null,
        ]);

        $settings = new SettingsService($this->user);
        $service = new InvoiceCreationService($settings);

        $invoice = $service->createFromProject($project);

        expect($invoice->items)->toHaveCount(1);
        expect($invoice->items->first()->quantity)->toBe('1.00');
    });

    it('does not add time entries line item if no unbilled entries', function () {
        $project = Project::factory()->hourly()->inProgress()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'hourly_rate' => 100.00,
        ]);

        $settings = new SettingsService($this->user);
        $service = new InvoiceCreationService($settings);

        $invoice = $service->createFromProject($project);

        expect($invoice->items)->toHaveCount(0);
    });
});
