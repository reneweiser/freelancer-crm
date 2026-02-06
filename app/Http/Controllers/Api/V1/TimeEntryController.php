<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreTimeEntryRequest;
use App\Http\Requests\Api\V1\UpdateTimeEntryRequest;
use App\Http\Resources\Api\V1\TimeEntryResource;
use App\Models\Project;
use App\Models\TimeEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimeEntryController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = TimeEntry::query()
            ->where('user_id', $request->user()->id)
            ->with('project');

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('description', 'like', "%{$search}%");
        }

        if ($request->has('project_id')) {
            $query->where('project_id', $request->input('project_id'));
        }

        if ($request->has('billable')) {
            $query->where('billable', filter_var($request->input('billable'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('invoiced')) {
            $invoiced = filter_var($request->input('invoiced'), FILTER_VALIDATE_BOOLEAN);
            $invoiced ? $query->invoiced() : $query->unbilled();
        }

        if ($request->has('date_from')) {
            $query->where('started_at', '>=', $request->input('date_from'));
        }

        if ($request->has('date_to')) {
            $query->where('started_at', '<=', $request->input('date_to'));
        }

        $perPage = min($request->input('per_page', 15), 100);
        $entries = $query->orderByDesc('started_at')->paginate($perPage);

        return $this->paginated(TimeEntryResource::collection($entries));
    }

    public function store(StoreTimeEntryRequest $request): JsonResponse
    {
        $project = Project::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($request->project_id);

        if (! $project->isHourly()) {
            return $this->error(
                'PROJECT_NOT_HOURLY',
                'Time entries can only be added to hourly projects.',
                422,
                ['Change the project type to "hourly" or select a different project.']
            );
        }

        $entry = TimeEntry::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return $this->created(new TimeEntryResource($entry->load('project')));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $entry = TimeEntry::query()
            ->where('user_id', $request->user()->id)
            ->with('project')
            ->findOrFail($id);

        return $this->resource(new TimeEntryResource($entry));
    }

    public function update(UpdateTimeEntryRequest $request, int $id): JsonResponse
    {
        $entry = TimeEntry::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        if ($entry->isInvoiced()) {
            return $this->error(
                'TIME_ENTRY_INVOICED',
                'Cannot update an invoiced time entry.',
                422,
                ['Remove the time entry from the invoice first.']
            );
        }

        $entry->update($request->validated());

        return $this->resource(new TimeEntryResource($entry->fresh('project')));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $entry = TimeEntry::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        if ($entry->isInvoiced()) {
            return $this->error(
                'TIME_ENTRY_INVOICED',
                'Cannot delete an invoiced time entry.',
                422,
                ['Remove the time entry from the invoice first.']
            );
        }

        $entry->delete();

        return $this->success(['deleted' => true]);
    }

    public function start(Request $request): JsonResponse
    {
        $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $project = Project::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($request->project_id);

        if (! $project->isHourly()) {
            return $this->error(
                'PROJECT_NOT_HOURLY',
                'Time entries can only be added to hourly projects.',
                422
            );
        }

        // Check for existing running timer
        $running = TimeEntry::query()
            ->where('user_id', $request->user()->id)
            ->whereNotNull('started_at')
            ->whereNull('ended_at')
            ->whereNull('duration_minutes')
            ->exists();

        if ($running) {
            return $this->error(
                'TIMER_ALREADY_RUNNING',
                'You already have a running timer. Stop it before starting a new one.',
                422,
                ['Use POST /api/v1/time-entries/{id}/stop to stop the running timer.']
            );
        }

        $entry = TimeEntry::create([
            'user_id' => $request->user()->id,
            'project_id' => $project->id,
            'description' => $request->input('description'),
            'started_at' => now(),
            'billable' => true,
        ]);

        return $this->created(new TimeEntryResource($entry->load('project')));
    }

    public function stop(Request $request, int $id): JsonResponse
    {
        $entry = TimeEntry::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $isRunning = $entry->started_at !== null && $entry->ended_at === null && $entry->duration_minutes === null;

        if (! $isRunning) {
            return $this->error(
                'TIMER_NOT_RUNNING',
                'This time entry is not currently running.',
                422
            );
        }

        $entry->update(['ended_at' => now()]);

        return $this->resource(new TimeEntryResource($entry->fresh('project')));
    }
}
