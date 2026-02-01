<?php

use App\Enums\ProjectStatus;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->client = Client::factory()->create(['user_id' => $this->user->id]);
});

describe('Project status transitions', function () {
    it('can transition from draft to sent', function () {
        $project = Project::factory()->draft()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $project->sendOffer();

        expect($project->fresh())
            ->status->toBe(ProjectStatus::Sent)
            ->offer_sent_at->not->toBeNull();
    });

    it('can transition from sent to accepted', function () {
        $project = Project::factory()->sent()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $project->acceptOffer();

        expect($project->fresh())
            ->status->toBe(ProjectStatus::Accepted)
            ->offer_accepted_at->not->toBeNull();
    });

    it('can transition from sent to declined', function () {
        $project = Project::factory()->sent()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $project->declineOffer();

        expect($project->fresh()->status)->toBe(ProjectStatus::Declined);
    });

    it('can transition from accepted to in progress', function () {
        $project = Project::factory()->accepted()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        Carbon::setTestNow(now());
        $project->startProject();

        expect($project->fresh())
            ->status->toBe(ProjectStatus::InProgress)
            ->start_date->toDateString()->toBe(now()->toDateString());
    });

    it('can transition from in progress to completed', function () {
        $project = Project::factory()->inProgress()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        Carbon::setTestNow(now());
        $project->completeProject();

        expect($project->fresh())
            ->status->toBe(ProjectStatus::Completed)
            ->end_date->toDateString()->toBe(now()->toDateString());
    });

    it('can reopen a completed project', function () {
        $project = Project::factory()->completed()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $project->reopenProject();

        expect($project->fresh())
            ->status->toBe(ProjectStatus::InProgress)
            ->end_date->toBeNull();
    });

    it('can cancel a project', function () {
        $project = Project::factory()->inProgress()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $project->cancel();

        expect($project->fresh()->status)->toBe(ProjectStatus::Cancelled);
    });
});

describe('Invalid project transitions', function () {
    it('cannot transition from draft directly to in progress', function () {
        $project = Project::factory()->draft()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        expect(fn () => $project->transitionTo(ProjectStatus::InProgress))
            ->toThrow(InvalidArgumentException::class);
    });

    it('cannot transition from declined to any state', function () {
        $project = Project::factory()->declined()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        expect(fn () => $project->transitionTo(ProjectStatus::InProgress))
            ->toThrow(InvalidArgumentException::class);
    });

    it('cannot transition from cancelled to any state', function () {
        $project = Project::factory()->cancelled()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        expect(fn () => $project->transitionTo(ProjectStatus::Draft))
            ->toThrow(InvalidArgumentException::class);
    });
});

describe('Project invoiceability', function () {
    it('can be invoiced when in progress', function () {
        $project = Project::factory()->inProgress()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        expect($project->canBeInvoiced())->toBeTrue();
    });

    it('can be invoiced when completed', function () {
        $project = Project::factory()->completed()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        expect($project->canBeInvoiced())->toBeTrue();
    });

    it('can be invoiced when accepted', function () {
        $project = Project::factory()->accepted()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        expect($project->canBeInvoiced())->toBeTrue();
    });

    it('cannot be invoiced when draft', function () {
        $project = Project::factory()->draft()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        expect($project->canBeInvoiced())->toBeFalse();
    });

    it('cannot be invoiced when cancelled', function () {
        $project = Project::factory()->cancelled()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        expect($project->canBeInvoiced())->toBeFalse();
    });
});
