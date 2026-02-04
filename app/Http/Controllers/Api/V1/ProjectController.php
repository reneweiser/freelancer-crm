<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProjectStatus;
use App\Http\Requests\Api\V1\StoreProjectRequest;
use App\Http\Requests\Api\V1\UpdateProjectRequest;
use App\Http\Resources\Api\V1\ProjectResource;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Project::query()
            ->where('user_id', $request->user()->id)
            ->with(['client', 'items']);

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('client_id')) {
            $query->where('client_id', $request->input('client_id'));
        }

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        $perPage = min($request->input('per_page', 15), 100);
        $projects = $query->orderByDesc('created_at')->paginate($perPage);

        return $this->paginated(ProjectResource::collection($projects));
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        // Verify client belongs to user
        $client = Client::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($request->client_id);

        return DB::transaction(function () use ($request) {
            $project = Project::create([
                ...$request->safe()->except('items'),
                'user_id' => $request->user()->id,
                'status' => ProjectStatus::Draft,
            ]);

            // Create items if provided
            if ($request->has('items')) {
                foreach ($request->items as $index => $item) {
                    $project->items()->create([
                        ...$item,
                        'position' => $index + 1,
                    ]);
                }
            }

            return $this->created(
                new ProjectResource($project->load(['client', 'items']))
            );
        });
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $project = Project::query()
            ->where('user_id', $request->user()->id)
            ->with(['client', 'items'])
            ->findOrFail($id);

        return $this->resource(new ProjectResource($project));
    }

    public function update(UpdateProjectRequest $request, int $id): JsonResponse
    {
        $project = Project::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        // If client_id is changing, verify new client belongs to user
        if ($request->has('client_id') && $request->client_id !== $project->client_id) {
            Client::query()
                ->where('user_id', $request->user()->id)
                ->findOrFail($request->client_id);
        }

        return DB::transaction(function () use ($request, $project) {
            $project->update($request->safe()->except('items'));

            // Update items if provided
            if ($request->has('items')) {
                $existingIds = [];

                foreach ($request->items as $index => $itemData) {
                    if (isset($itemData['id'])) {
                        // Update existing item
                        $item = $project->items()->find($itemData['id']);
                        if ($item) {
                            $item->update([
                                ...$itemData,
                                'position' => $index + 1,
                            ]);
                            $existingIds[] = $item->id;
                        }
                    } else {
                        // Create new item
                        $item = $project->items()->create([
                            ...$itemData,
                            'position' => $index + 1,
                        ]);
                        $existingIds[] = $item->id;
                    }
                }

                // Delete items not in the update
                $project->items()->whereNotIn('id', $existingIds)->delete();
            }

            return $this->resource(
                new ProjectResource($project->fresh(['client', 'items']))
            );
        });
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $project = Project::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        // Check for related invoices
        $invoicesCount = $project->invoices()->count();

        if ($invoicesCount > 0) {
            return $this->error(
                'PROJECT_HAS_INVOICES',
                'Cannot delete project with existing invoices.',
                422,
                [
                    'Project has '.$invoicesCount.' invoice(s).',
                    'Delete related invoices first or cancel them.',
                ]
            );
        }

        $project->delete();

        return $this->success(['deleted' => true]);
    }

    public function transition(Request $request, int $id): JsonResponse
    {
        $project = Project::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $request->validate([
            'status' => ['required', 'string'],
        ]);

        $newStatus = ProjectStatus::tryFrom($request->status);

        if (! $newStatus) {
            return $this->error(
                'INVALID_STATUS',
                'Invalid status value.',
                422,
                [
                    'Valid statuses: '.implode(', ', array_map(fn ($s) => $s->value, ProjectStatus::cases())),
                ]
            );
        }

        if (! $project->status->canTransitionTo($newStatus)) {
            return $this->error(
                'INVALID_TRANSITION',
                "Cannot transition from '{$project->status->value}' to '{$newStatus->value}'.",
                422,
                [
                    'Current status: '.$project->status->value,
                    'Allowed transitions: '.implode(', ', array_map(fn ($s) => $s->value, $project->status->allowedTransitions())),
                ]
            );
        }

        // Perform the transition using model methods
        match ($newStatus) {
            ProjectStatus::Sent => $project->sendOffer(),
            ProjectStatus::Accepted => $project->acceptOffer(),
            ProjectStatus::Declined => $project->declineOffer(),
            ProjectStatus::InProgress => $project->status === ProjectStatus::Completed
                ? $project->reopenProject()
                : $project->startProject($request->input('start_date')),
            ProjectStatus::Completed => $project->completeProject($request->input('end_date')),
            ProjectStatus::Cancelled => $project->cancel(),
            default => $project->update(['status' => $newStatus]),
        };

        return $this->resource(
            new ProjectResource($project->fresh(['client', 'items']))
        );
    }
}
