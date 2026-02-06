<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreRecurringTaskRequest;
use App\Http\Requests\Api\V1\UpdateRecurringTaskRequest;
use App\Http\Resources\Api\V1\RecurringTaskResource;
use App\Models\Client;
use App\Models\RecurringTask;
use App\Services\RecurringTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecurringTaskController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = RecurringTask::query()
            ->with('client');

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('client_id')) {
            $query->where('client_id', $request->input('client_id'));
        }

        if ($request->has('frequency')) {
            $query->where('frequency', $request->input('frequency'));
        }

        if ($request->has('active')) {
            $active = filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN);
            $active ? $query->active() : $query->inactive();
        }

        if ($request->has('overdue') && filter_var($request->input('overdue'), FILTER_VALIDATE_BOOLEAN)) {
            $query->overdue();
        }

        $perPage = min($request->input('per_page', 15), 100);
        $tasks = $query->orderBy('next_due_at')->paginate($perPage);

        return $this->paginated(RecurringTaskResource::collection($tasks));
    }

    public function store(StoreRecurringTaskRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Verify client belongs to user if provided
        if (! empty($data['client_id'])) {
            Client::query()
                ->where('user_id', $request->user()->id)
                ->findOrFail($data['client_id']);
        }

        $task = RecurringTask::create([
            ...$data,
            'user_id' => $request->user()->id,
        ]);

        return $this->created(new RecurringTaskResource($task->fresh('client')));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $task = RecurringTask::query()
            ->with(['client', 'logs' => fn ($q) => $q->latest()->limit(10)])
            ->findOrFail($id);

        return $this->resource(new RecurringTaskResource($task));
    }

    public function update(UpdateRecurringTaskRequest $request, int $id): JsonResponse
    {
        $task = RecurringTask::findOrFail($id);

        $data = $request->validated();

        // Verify client belongs to user if changing client
        if (! empty($data['client_id'])) {
            Client::query()
                ->where('user_id', $request->user()->id)
                ->findOrFail($data['client_id']);
        }

        $task->update($data);

        return $this->resource(new RecurringTaskResource($task->fresh('client')));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $task = RecurringTask::findOrFail($id);

        $task->delete();

        return $this->success(['deleted' => true]);
    }

    public function pause(Request $request, int $id): JsonResponse
    {
        $task = RecurringTask::findOrFail($id);

        if (! $task->active) {
            return $this->error(
                'TASK_ALREADY_PAUSED',
                'Task is already paused.',
                422
            );
        }

        $task->pause();

        return $this->resource(new RecurringTaskResource($task->fresh('client')));
    }

    public function resume(Request $request, int $id): JsonResponse
    {
        $task = RecurringTask::findOrFail($id);

        if ($task->active) {
            return $this->error(
                'TASK_ALREADY_ACTIVE',
                'Task is already active.',
                422
            );
        }

        $task->resume();

        return $this->resource(new RecurringTaskResource($task->fresh('client')));
    }

    public function skip(Request $request, int $id, RecurringTaskService $service): JsonResponse
    {
        $task = RecurringTask::findOrFail($id);

        if (! $task->active) {
            return $this->error(
                'TASK_NOT_ACTIVE',
                'Cannot skip an inactive task.',
                422,
                ['Resume the task first, then skip it.']
            );
        }

        $reason = $request->input('reason');
        $service->skipOccurrence($task, $reason);

        return $this->resource(new RecurringTaskResource($task->fresh('client')));
    }

    public function advance(Request $request, int $id): JsonResponse
    {
        $task = RecurringTask::findOrFail($id);

        if (! $task->active) {
            return $this->error(
                'TASK_NOT_ACTIVE',
                'Cannot advance an inactive task.',
                422,
                ['Resume the task first, then advance it.']
            );
        }

        $task->advance();

        return $this->resource(new RecurringTaskResource($task->fresh('client')));
    }
}
