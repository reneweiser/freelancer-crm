<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreReminderRequest;
use App\Http\Requests\Api\V1\UpdateReminderRequest;
use App\Http\Resources\Api\V1\ReminderResource;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Reminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReminderController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Reminder::query()
            ->where('user_id', $request->user()->id)
            ->with('remindable');

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        if ($request->has('status')) {
            match ($request->input('status')) {
                'pending' => $query->pending(),
                'completed' => $query->completed(),
                'overdue' => $query->overdue(),
                'due' => $query->due(),
                default => null,
            };
        }

        if ($request->has('remindable_type')) {
            $type = match ($request->input('remindable_type')) {
                'Client' => Client::class,
                'Project' => Project::class,
                'Invoice' => Invoice::class,
                default => null,
            };
            if ($type) {
                $query->where('remindable_type', $type);
            }
        }

        if ($request->has('remindable_id')) {
            $query->where('remindable_id', $request->input('remindable_id'));
        }

        if ($request->has('upcoming_days')) {
            $query->upcoming((int) $request->input('upcoming_days'));
        }

        $perPage = min($request->input('per_page', 15), 100);
        $reminders = $query->orderBy('due_at')->paginate($perPage);

        return $this->paginated(ReminderResource::collection($reminders));
    }

    public function store(StoreReminderRequest $request): JsonResponse
    {
        $data = [
            'user_id' => $request->user()->id,
            'title' => $request->title,
            'description' => $request->description,
            'due_at' => $request->due_at,
            'priority' => $request->priority ?? 'normal',
            'recurrence' => $request->recurrence,
        ];

        // Handle remindable attachment
        if ($request->remindable_type && $request->remindable_id) {
            $remindableClass = $request->getRemindableClass();

            // Verify the remindable belongs to the user
            $remindable = $this->findRemindable(
                $remindableClass,
                $request->remindable_id,
                $request->user()->id
            );

            if (! $remindable) {
                return $this->error(
                    'REMINDABLE_NOT_FOUND',
                    'The specified '.$request->remindable_type.' was not found.',
                    404,
                    ['Verify the '.$request->remindable_type.' ID exists and belongs to you.']
                );
            }

            $data['remindable_type'] = $remindableClass;
            $data['remindable_id'] = $request->remindable_id;
        }

        $reminder = Reminder::create($data);

        return $this->created(new ReminderResource($reminder->load('remindable')));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $reminder = Reminder::query()
            ->where('user_id', $request->user()->id)
            ->with('remindable')
            ->findOrFail($id);

        return $this->resource(new ReminderResource($reminder));
    }

    public function update(UpdateReminderRequest $request, int $id): JsonResponse
    {
        $reminder = Reminder::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        // Don't allow updating system reminders
        if ($reminder->is_system) {
            return $this->error(
                'SYSTEM_REMINDER',
                'System-generated reminders cannot be updated.',
                422,
                ['You can only complete or snooze system reminders.']
            );
        }

        $data = $request->safe()->except(['remindable_type', 'remindable_id']);

        // Handle remindable attachment change
        if ($request->has('remindable_type')) {
            if ($request->remindable_type && $request->remindable_id) {
                $remindableClass = $request->getRemindableClass();

                $remindable = $this->findRemindable(
                    $remindableClass,
                    $request->remindable_id,
                    $request->user()->id
                );

                if (! $remindable) {
                    return $this->error(
                        'REMINDABLE_NOT_FOUND',
                        'The specified '.$request->remindable_type.' was not found.',
                        404
                    );
                }

                $data['remindable_type'] = $remindableClass;
                $data['remindable_id'] = $request->remindable_id;
            } else {
                // Clear the attachment
                $data['remindable_type'] = null;
                $data['remindable_id'] = null;
            }
        }

        $reminder->update($data);

        return $this->resource(new ReminderResource($reminder->fresh('remindable')));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $reminder = Reminder::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $reminder->delete();

        return $this->success(['deleted' => true]);
    }

    public function complete(Request $request, int $id): JsonResponse
    {
        $reminder = Reminder::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        if ($reminder->completed_at) {
            return $this->error(
                'ALREADY_COMPLETED',
                'Reminder is already completed.',
                422
            );
        }

        $reminder->complete();

        // If recurring, return the new reminder
        if ($reminder->recurrence) {
            $newReminder = Reminder::pending()
                ->where('title', $reminder->title)
                ->where('user_id', $request->user()->id)
                ->latest('id')
                ->first();

            return $this->success([
                'completed' => new ReminderResource($reminder->fresh('remindable')),
                'next_occurrence' => $newReminder ? new ReminderResource($newReminder->load('remindable')) : null,
            ]);
        }

        return $this->resource(new ReminderResource($reminder->fresh('remindable')));
    }

    public function snooze(Request $request, int $id): JsonResponse
    {
        $reminder = Reminder::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        if ($reminder->completed_at) {
            return $this->error(
                'ALREADY_COMPLETED',
                'Cannot snooze a completed reminder.',
                422
            );
        }

        $validated = $request->validate([
            'hours' => ['nullable', 'integer', 'min:1', 'max:720'],
        ]);

        $hours = $validated['hours'] ?? 24;
        $reminder->snooze($hours);

        return $this->resource(new ReminderResource($reminder->fresh('remindable')));
    }

    /**
     * Find a remindable model by type, ID, and user.
     */
    private function findRemindable(string $class, int $id, int $userId): ?object
    {
        return match ($class) {
            Client::class => Client::where('user_id', $userId)->find($id),
            Project::class => Project::where('user_id', $userId)->find($id),
            Invoice::class => Invoice::where('user_id', $userId)->find($id),
            default => null,
        };
    }
}
