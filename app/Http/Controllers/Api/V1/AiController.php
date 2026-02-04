<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProjectStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Reminder;
use App\Services\InvoiceCreationService;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AiController extends ApiController
{
    /**
     * Execute multiple operations in a single transaction.
     */
    public function batch(Request $request): JsonResponse
    {
        $request->validate([
            'operations' => ['required', 'array', 'min:1', 'max:50'],
            'operations.*.action' => ['required', 'string'],
            'operations.*.resource' => ['required', 'string'],
            'operations.*.data' => ['nullable', 'array'],
            'operations.*.id' => ['nullable'],
        ]);

        $operations = $request->input('operations');
        $results = [];
        $references = [];

        try {
            DB::beginTransaction();

            foreach ($operations as $index => $operation) {
                $result = $this->executeOperation(
                    $request->user(),
                    $operation,
                    $references
                );

                $results[] = [
                    'index' => $index,
                    'success' => true,
                    'data' => $result['data'],
                    'ref' => $result['ref'] ?? null,
                ];

                // Store reference for later operations
                if (isset($result['ref'])) {
                    $references[$result['ref']] = $result['data']['id'];
                }
            }

            DB::commit();

            return $this->success([
                'batch_id' => uniqid('batch_'),
                'total' => count($operations),
                'succeeded' => count($results),
                'failed' => 0,
                'results' => $results,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error(
                'BATCH_FAILED',
                $e->getMessage(),
                422,
                [
                    'All operations have been rolled back.',
                    'Fix the error and retry the entire batch.',
                ]
            );
        }
    }

    /**
     * Validate operations without executing them.
     */
    public function validate(Request $request): JsonResponse
    {
        $request->validate([
            'operations' => ['required', 'array', 'min:1', 'max:50'],
            'operations.*.action' => ['required', 'string'],
            'operations.*.resource' => ['required', 'string'],
            'operations.*.data' => ['nullable', 'array'],
            'operations.*.id' => ['nullable'],
        ]);

        $operations = $request->input('operations');
        $validations = [];
        $hasErrors = false;

        foreach ($operations as $index => $operation) {
            $validation = $this->validateOperation(
                $request->user(),
                $operation
            );

            $validations[] = [
                'index' => $index,
                'valid' => $validation['valid'],
                'errors' => $validation['errors'] ?? null,
                'warnings' => $validation['warnings'] ?? null,
            ];

            if (! $validation['valid']) {
                $hasErrors = true;
            }
        }

        return $this->success([
            'valid' => ! $hasErrors,
            'total' => count($operations),
            'validations' => $validations,
        ]);
    }

    /**
     * Execute a single operation.
     *
     * @param  array<string, mixed>  $operation
     * @param  array<string, int>  $references
     * @return array<string, mixed>
     */
    private function executeOperation($user, array $operation, array $references): array
    {
        $action = $operation['action'];
        $resource = $operation['resource'];
        $data = $this->resolveReferences($operation['data'] ?? [], $references);
        $id = $this->resolveReference($operation['id'] ?? null, $references);

        return match ($resource) {
            'client', 'clients' => $this->handleClientOperation($user, $action, $data, $id),
            'project', 'projects' => $this->handleProjectOperation($user, $action, $data, $id),
            'invoice', 'invoices' => $this->handleInvoiceOperation($user, $action, $data, $id),
            'reminder', 'reminders' => $this->handleReminderOperation($user, $action, $data, $id),
            default => throw new \InvalidArgumentException("Unknown resource: {$resource}"),
        };
    }

    /**
     * Validate a single operation without executing.
     *
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function validateOperation($user, array $operation): array
    {
        $action = $operation['action'];
        $resource = $operation['resource'];
        $data = $operation['data'] ?? [];
        $id = $operation['id'] ?? null;

        $errors = [];
        $warnings = [];

        // Validate resource type
        if (! in_array($resource, ['client', 'clients', 'project', 'projects', 'invoice', 'invoices', 'reminder', 'reminders'])) {
            $errors[] = "Unknown resource: {$resource}";

            return ['valid' => false, 'errors' => $errors];
        }

        // Validate action
        $validActions = ['create', 'update', 'delete', 'transition', 'from_project', 'mark_paid', 'complete', 'snooze'];
        if (! in_array($action, $validActions)) {
            $errors[] = "Unknown action: {$action}. Valid actions: ".implode(', ', $validActions);

            return ['valid' => false, 'errors' => $errors];
        }

        // Validate ID for update/delete actions
        if (in_array($action, ['update', 'delete', 'transition', 'mark_paid', 'complete', 'snooze'])) {
            if (empty($id) && ! str_starts_with((string) $id, '$ref:')) {
                $errors[] = 'ID is required for '.$action.' action.';
            }
        }

        // Resource-specific validation
        $resourceValidation = match ($resource) {
            'client', 'clients' => $this->validateClientData($action, $data),
            'project', 'projects' => $this->validateProjectData($user, $action, $data),
            'invoice', 'invoices' => $this->validateInvoiceData($user, $action, $data),
            'reminder', 'reminders' => $this->validateReminderData($action, $data),
            default => ['errors' => [], 'warnings' => []],
        };

        $errors = array_merge($errors, $resourceValidation['errors']);
        $warnings = array_merge($warnings, $resourceValidation['warnings']);

        return [
            'valid' => empty($errors),
            'errors' => empty($errors) ? null : $errors,
            'warnings' => empty($warnings) ? null : $warnings,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function handleClientOperation($user, string $action, array $data, $id): array
    {
        return match ($action) {
            'create' => $this->createClient($user, $data),
            'update' => $this->updateClient($user, $id, $data),
            'delete' => $this->deleteClient($user, $id),
            default => throw new \InvalidArgumentException("Invalid action for client: {$action}"),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function handleProjectOperation($user, string $action, array $data, $id): array
    {
        return match ($action) {
            'create' => $this->createProject($user, $data),
            'update' => $this->updateProject($user, $id, $data),
            'delete' => $this->deleteProject($user, $id),
            'transition' => $this->transitionProject($user, $id, $data),
            default => throw new \InvalidArgumentException("Invalid action for project: {$action}"),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function handleInvoiceOperation($user, string $action, array $data, $id): array
    {
        return match ($action) {
            'create' => $this->createInvoice($user, $data),
            'from_project' => $this->createInvoiceFromProject($user, $data),
            'mark_paid' => $this->markInvoicePaid($user, $id, $data),
            'delete' => $this->deleteInvoice($user, $id),
            default => throw new \InvalidArgumentException("Invalid action for invoice: {$action}"),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function handleReminderOperation($user, string $action, array $data, $id): array
    {
        return match ($action) {
            'create' => $this->createReminder($user, $data),
            'update' => $this->updateReminder($user, $id, $data),
            'delete' => $this->deleteReminder($user, $id),
            'complete' => $this->completeReminder($user, $id),
            'snooze' => $this->snoozeReminder($user, $id, $data),
            default => throw new \InvalidArgumentException("Invalid action for reminder: {$action}"),
        };
    }

    // Client operations

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function createClient($user, array $data): array
    {
        $client = Client::create([
            ...$data,
            'user_id' => $user->id,
        ]);

        return [
            'data' => ['id' => $client->id, 'type' => 'client'],
            'ref' => $data['$ref'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function updateClient($user, $id, array $data): array
    {
        $client = Client::where('user_id', $user->id)->findOrFail($id);
        $client->update($data);

        return ['data' => ['id' => $client->id, 'type' => 'client']];
    }

    /**
     * @return array<string, mixed>
     */
    private function deleteClient($user, $id): array
    {
        $client = Client::where('user_id', $user->id)->findOrFail($id);
        $client->delete();

        return ['data' => ['id' => $id, 'deleted' => true]];
    }

    // Project operations

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function createProject($user, array $data): array
    {
        // Verify client belongs to user
        Client::where('user_id', $user->id)->findOrFail($data['client_id']);

        $items = $data['items'] ?? [];
        unset($data['items']);

        $project = Project::create([
            ...$data,
            'user_id' => $user->id,
            'status' => ProjectStatus::Draft,
        ]);

        foreach ($items as $index => $item) {
            $project->items()->create([...$item, 'position' => $index + 1]);
        }

        return [
            'data' => ['id' => $project->id, 'type' => 'project'],
            'ref' => $data['$ref'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function updateProject($user, $id, array $data): array
    {
        $project = Project::where('user_id', $user->id)->findOrFail($id);
        $project->update($data);

        return ['data' => ['id' => $project->id, 'type' => 'project']];
    }

    /**
     * @return array<string, mixed>
     */
    private function deleteProject($user, $id): array
    {
        $project = Project::where('user_id', $user->id)->findOrFail($id);
        $project->delete();

        return ['data' => ['id' => $id, 'deleted' => true]];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function transitionProject($user, $id, array $data): array
    {
        $project = Project::where('user_id', $user->id)->findOrFail($id);
        $newStatus = ProjectStatus::from($data['status']);

        if (! $project->status->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException(
                "Cannot transition from '{$project->status->value}' to '{$newStatus->value}'."
            );
        }

        match ($newStatus) {
            ProjectStatus::Sent => $project->sendOffer(),
            ProjectStatus::Accepted => $project->acceptOffer(),
            ProjectStatus::Declined => $project->declineOffer(),
            ProjectStatus::InProgress => $project->status === ProjectStatus::Completed
                ? $project->reopenProject()
                : $project->startProject($data['start_date'] ?? null),
            ProjectStatus::Completed => $project->completeProject($data['end_date'] ?? null),
            ProjectStatus::Cancelled => $project->cancel(),
            default => $project->update(['status' => $newStatus]),
        };

        return ['data' => ['id' => $project->id, 'status' => $newStatus->value]];
    }

    // Invoice operations

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function createInvoice($user, array $data): array
    {
        Client::where('user_id', $user->id)->findOrFail($data['client_id']);

        $items = $data['items'] ?? [];
        unset($data['items']);

        $settings = new SettingsService($user);
        $defaultVatRate = (float) ($settings->get('default_vat_rate', 19.00));

        $invoice = Invoice::create([
            ...$data,
            'user_id' => $user->id,
            'number' => Invoice::generateNextNumber(),
            'status' => \App\Enums\InvoiceStatus::Draft,
            'vat_rate' => $data['vat_rate'] ?? $defaultVatRate,
        ]);

        foreach ($items as $index => $item) {
            $invoice->items()->create([
                ...$item,
                'vat_rate' => $item['vat_rate'] ?? $defaultVatRate,
                'position' => $index + 1,
            ]);
        }

        $invoice->refresh();
        $invoice->calculateTotals();
        $invoice->save();

        return [
            'data' => ['id' => $invoice->id, 'number' => $invoice->number, 'type' => 'invoice'],
            'ref' => $data['$ref'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function createInvoiceFromProject($user, array $data): array
    {
        $project = Project::where('user_id', $user->id)->findOrFail($data['project_id']);

        if (! $project->canBeInvoiced()) {
            throw new \InvalidArgumentException(
                "Project in status '{$project->status->getLabel()}' cannot be invoiced."
            );
        }

        $settings = new SettingsService($user);
        $service = new InvoiceCreationService($settings);
        $invoice = $service->createFromProject($project);

        return [
            'data' => ['id' => $invoice->id, 'number' => $invoice->number, 'type' => 'invoice'],
            'ref' => $data['$ref'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function markInvoicePaid($user, $id, array $data): array
    {
        $invoice = Invoice::where('user_id', $user->id)->findOrFail($id);
        $invoice->markAsPaid($data);

        return ['data' => ['id' => $invoice->id, 'status' => 'paid']];
    }

    /**
     * @return array<string, mixed>
     */
    private function deleteInvoice($user, $id): array
    {
        $invoice = Invoice::where('user_id', $user->id)->findOrFail($id);
        $invoice->delete();

        return ['data' => ['id' => $id, 'deleted' => true]];
    }

    // Reminder operations

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function createReminder($user, array $data): array
    {
        // Handle remindable type mapping
        if (isset($data['remindable_type'])) {
            $data['remindable_type'] = match ($data['remindable_type']) {
                'Client' => Client::class,
                'Project' => Project::class,
                'Invoice' => Invoice::class,
                default => $data['remindable_type'],
            };
        }

        $reminder = Reminder::create([
            ...$data,
            'user_id' => $user->id,
        ]);

        return [
            'data' => ['id' => $reminder->id, 'type' => 'reminder'],
            'ref' => $data['$ref'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function updateReminder($user, $id, array $data): array
    {
        $reminder = Reminder::withoutGlobalScope('user')
            ->where('user_id', $user->id)
            ->findOrFail($id);
        $reminder->update($data);

        return ['data' => ['id' => $reminder->id, 'type' => 'reminder']];
    }

    /**
     * @return array<string, mixed>
     */
    private function deleteReminder($user, $id): array
    {
        $reminder = Reminder::withoutGlobalScope('user')
            ->where('user_id', $user->id)
            ->findOrFail($id);
        $reminder->delete();

        return ['data' => ['id' => $id, 'deleted' => true]];
    }

    /**
     * @return array<string, mixed>
     */
    private function completeReminder($user, $id): array
    {
        $reminder = Reminder::withoutGlobalScope('user')
            ->where('user_id', $user->id)
            ->findOrFail($id);
        $reminder->complete();

        return ['data' => ['id' => $reminder->id, 'completed' => true]];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function snoozeReminder($user, $id, array $data): array
    {
        $reminder = Reminder::withoutGlobalScope('user')
            ->where('user_id', $user->id)
            ->findOrFail($id);
        $reminder->snooze($data['hours'] ?? 24);

        return ['data' => ['id' => $reminder->id, 'snoozed' => true]];
    }

    // Validation helpers

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, array<string>>
     */
    private function validateClientData(string $action, array $data): array
    {
        $errors = [];
        $warnings = [];

        if ($action === 'create') {
            if (empty($data['type'])) {
                $errors[] = 'Client type is required.';
            } elseif (! in_array($data['type'], ['company', 'individual'])) {
                $errors[] = 'Invalid client type. Use "company" or "individual".';
            }

            if (empty($data['contact_name'])) {
                $errors[] = 'Contact name is required.';
            }

            if (empty($data['email'])) {
                $errors[] = 'Email is required.';
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, array<string>>
     */
    private function validateProjectData($user, string $action, array $data): array
    {
        $errors = [];
        $warnings = [];

        if ($action === 'create') {
            if (empty($data['client_id'])) {
                $errors[] = 'Client ID is required.';
            }

            if (empty($data['title'])) {
                $errors[] = 'Project title is required.';
            }

            if (empty($data['type'])) {
                $errors[] = 'Project type is required.';
            } elseif (! in_array($data['type'], ['fixed', 'hourly'])) {
                $errors[] = 'Invalid project type. Use "fixed" or "hourly".';
            }
        }

        if ($action === 'transition') {
            if (empty($data['status'])) {
                $errors[] = 'Status is required for transition.';
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, array<string>>
     */
    private function validateInvoiceData($user, string $action, array $data): array
    {
        $errors = [];
        $warnings = [];

        if ($action === 'create') {
            if (empty($data['client_id'])) {
                $errors[] = 'Client ID is required.';
            }

            if (empty($data['items']) || ! is_array($data['items']) || count($data['items']) === 0) {
                $errors[] = 'At least one item is required.';
            }
        }

        if ($action === 'from_project') {
            if (empty($data['project_id'])) {
                $errors[] = 'Project ID is required.';
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, array<string>>
     */
    private function validateReminderData(string $action, array $data): array
    {
        $errors = [];
        $warnings = [];

        if ($action === 'create') {
            if (empty($data['title'])) {
                $errors[] = 'Reminder title is required.';
            }

            if (empty($data['due_at'])) {
                $errors[] = 'Due date is required.';
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Resolve $ref: references in data.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, int>  $references
     * @return array<string, mixed>
     */
    private function resolveReferences(array $data, array $references): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value) && str_starts_with($value, '$ref:')) {
                $refName = substr($value, 5);
                if (! isset($references[$refName])) {
                    throw new \InvalidArgumentException("Unresolved reference: {$value}");
                }
                $data[$key] = $references[$refName];
            } elseif (is_array($value)) {
                $data[$key] = $this->resolveReferences($value, $references);
            }
        }

        return $data;
    }

    /**
     * Resolve a single reference.
     *
     * @param  array<string, int>  $references
     */
    private function resolveReference($value, array $references): mixed
    {
        if (is_string($value) && str_starts_with($value, '$ref:')) {
            $refName = substr($value, 5);
            if (! isset($references[$refName])) {
                throw new \InvalidArgumentException("Unresolved reference: {$value}");
            }

            return $references[$refName];
        }

        return $value;
    }
}
