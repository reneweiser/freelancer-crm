<?php

use App\Filament\Widgets\StatsOverviewWidget;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->client = Client::factory()->create(['user_id' => $this->user->id]);
    $this->actingAs($this->user);
});

describe('StatsOverviewWidget', function () {
    it('can render', function () {
        Livewire::test(StatsOverviewWidget::class)
            ->assertSuccessful();
    });

    it('displays open invoices count and total', function () {
        Invoice::factory()->sent()->withTotals(1000)->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        Invoice::factory()->overdue()->withTotals(500)->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        Invoice::factory()->draft()->withTotals(200)->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        Livewire::test(StatsOverviewWidget::class)
            ->assertSee('Offene Rechnungen')
            ->assertSee('2');
    });

    it('displays monthly revenue for paid invoices', function () {
        Invoice::factory()->paid()->withTotals(2000)->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'paid_at' => now(),
        ]);

        Invoice::factory()->paid()->withTotals(1000)->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'paid_at' => now()->subMonth(),
        ]);

        Livewire::test(StatsOverviewWidget::class)
            ->assertSee('Umsatz diesen Monat');
    });

    it('displays yearly revenue for paid invoices', function () {
        Invoice::factory()->paid()->withTotals(5000)->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'paid_at' => now(),
        ]);

        Livewire::test(StatsOverviewWidget::class)
            ->assertSee('Umsatz dieses Jahr');
    });

    it('displays active projects count', function () {
        Project::factory()->inProgress()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        Project::factory()->inProgress()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        Project::factory()->completed()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        Livewire::test(StatsOverviewWidget::class)
            ->assertSee('Aktive Projekte')
            ->assertSee('2');
    });

    it('only shows data for authenticated user', function () {
        $otherUser = User::factory()->create();
        $otherClient = Client::factory()->create(['user_id' => $otherUser->id]);

        Invoice::factory()->sent()->withTotals(1000)->create([
            'user_id' => $otherUser->id,
            'client_id' => $otherClient->id,
        ]);

        Invoice::factory()->sent()->withTotals(500)->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        Livewire::test(StatsOverviewWidget::class)
            ->assertSee('Offene Rechnungen')
            ->assertSee('1');
    });
});
