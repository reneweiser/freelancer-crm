<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Reminder;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->client = Client::factory()->create(['user_id' => $this->user->id]);
    Sanctum::actingAs($this->user);
});

describe('Stats API', function () {
    it('returns aggregated stats', function () {
        $response = $this->getJson('/api/v1/stats');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'revenue' => [
                        'total_year',
                        'outstanding',
                        'monthly',
                    ],
                    'projects' => [
                        'total',
                        'by_status',
                        'active',
                        'offers_pending',
                    ],
                    'invoices' => [
                        'total_year',
                        'by_status',
                        'overdue_amount',
                    ],
                    'reminders' => [
                        'total_pending',
                        'overdue',
                        'due_today',
                        'upcoming_7_days',
                        'by_priority',
                    ],
                    'year',
                    'generated_at',
                ],
            ]);
    });

    it('returns revenue stats for paid invoices', function () {
        Invoice::factory()->paid()->withTotals(1000)->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'paid_at' => now(),
        ]);
        Invoice::factory()->paid()->withTotals(2000)->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'paid_at' => now(),
        ]);
        Invoice::factory()->sent()->withTotals(500)->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->getJson('/api/v1/stats');

        $response->assertOk();

        expect((float) $response->json('data.revenue.total_year'))->toBe(3570.0) // (1000 + 2000) * 1.19
            ->and((float) $response->json('data.revenue.outstanding'))->toBe(595.0); // 500 * 1.19
    });

    it('returns project counts by status', function () {
        Project::factory()->draft()->create([
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

        $response = $this->getJson('/api/v1/stats');

        $response->assertOk()
            ->assertJsonPath('data.projects.total', 3)
            ->assertJsonPath('data.projects.by_status.draft', 1)
            ->assertJsonPath('data.projects.by_status.in_progress', 1)
            ->assertJsonPath('data.projects.by_status.completed', 1)
            ->assertJsonPath('data.projects.active', 1);
    });

    it('returns reminder stats', function () {
        Reminder::factory()->create([
            'user_id' => $this->user->id,
            'due_at' => now()->addDays(3),
            'priority' => 'high',
        ]);
        Reminder::factory()->overdue()->create([
            'user_id' => $this->user->id,
        ]);
        Reminder::factory()->completed()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/stats');

        $response->assertOk()
            ->assertJsonPath('data.reminders.total_pending', 2)
            ->assertJsonPath('data.reminders.overdue', 1)
            ->assertJsonPath('data.reminders.by_priority.high', 1);
    });

    it('filters by year', function () {
        Invoice::factory()->paid()->withTotals(1000)->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'issued_at' => '2026-06-01',
            'paid_at' => '2026-06-15',
        ]);
        Invoice::factory()->paid()->withTotals(500)->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'issued_at' => '2025-06-01',
            'paid_at' => '2025-06-15',
        ]);

        $response = $this->getJson('/api/v1/stats?year=2026');

        $response->assertOk();

        expect((int) $response->json('data.year'))->toBe(2026)
            ->and((float) $response->json('data.revenue.total_year'))->toBe(1190.0)
            ->and($response->json('data.invoices.total_year'))->toBe(1);
    });

    it('only includes current user data', function () {
        // Other user's data
        $otherUser = User::factory()->create();
        $otherClient = Client::factory()->create(['user_id' => $otherUser->id]);
        Invoice::factory()->paid()->withTotals(5000)->create([
            'user_id' => $otherUser->id,
            'client_id' => $otherClient->id,
            'paid_at' => now(),
        ]);

        // Current user's data
        Invoice::factory()->paid()->withTotals(1000)->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'paid_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/stats');

        $response->assertOk();

        expect((float) $response->json('data.revenue.total_year'))->toBe(1190.0);
    });
});
