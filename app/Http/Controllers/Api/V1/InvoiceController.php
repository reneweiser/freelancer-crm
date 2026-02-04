<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InvoiceStatus;
use App\Http\Requests\Api\V1\StoreInvoiceRequest;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Services\InvoiceCreationService;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::query()
            ->where('user_id', $request->user()->id)
            ->with(['client', 'items']);

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($q) use ($search) {
                        $q->where('company_name', 'like', "%{$search}%")
                            ->orWhere('contact_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('client_id')) {
            $query->where('client_id', $request->input('client_id'));
        }

        if ($request->has('project_id')) {
            $query->where('project_id', $request->input('project_id'));
        }

        if ($request->has('year')) {
            $query->whereYear('issued_at', $request->input('year'));
        }

        $perPage = min($request->input('per_page', 15), 100);
        $invoices = $query->orderByDesc('issued_at')->paginate($perPage);

        return $this->paginated(InvoiceResource::collection($invoices));
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        // Verify client belongs to user
        $client = Client::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($request->client_id);

        // Verify project belongs to user if provided
        if ($request->project_id) {
            Project::query()
                ->where('user_id', $request->user()->id)
                ->findOrFail($request->project_id);
        }

        return DB::transaction(function () use ($request) {
            $settings = new SettingsService($request->user());
            $defaultVatRate = (float) ($settings->get('default_vat_rate', 19.00));
            $paymentTermDays = (int) ($settings->get('payment_terms_days', 14));

            $invoice = Invoice::create([
                'user_id' => $request->user()->id,
                'client_id' => $request->client_id,
                'project_id' => $request->project_id,
                'number' => Invoice::generateNextNumber(),
                'status' => InvoiceStatus::Draft,
                'issued_at' => $request->issued_at ?? now(),
                'due_at' => $request->due_at ?? now()->addDays($paymentTermDays),
                'vat_rate' => $request->vat_rate ?? $defaultVatRate,
                'service_period_start' => $request->service_period_start,
                'service_period_end' => $request->service_period_end,
                'notes' => $request->notes,
                'footer_text' => $request->footer_text ?? $settings->get('invoice_footer'),
            ]);

            // Create items
            foreach ($request->items as $index => $itemData) {
                $item = [
                    'description' => $itemData['description'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'vat_rate' => $itemData['vat_rate'] ?? $defaultVatRate,
                    'position' => $index + 1,
                ];
                if (isset($itemData['unit'])) {
                    $item['unit'] = $itemData['unit'];
                }
                $invoice->items()->create($item);
            }

            // Calculate totals
            $invoice->refresh();
            $invoice->calculateTotals();
            $invoice->save();

            return $this->created(
                new InvoiceResource($invoice->load(['client', 'items']))
            );
        });
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::query()
            ->where('user_id', $request->user()->id)
            ->with(['client', 'project', 'items'])
            ->findOrFail($id);

        return $this->resource(new InvoiceResource($invoice));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        // Only allow updating draft invoices
        if ($invoice->status !== InvoiceStatus::Draft) {
            return $this->error(
                'INVOICE_NOT_DRAFT',
                'Only draft invoices can be updated.',
                422,
                [
                    'Current status: '.$invoice->status->value,
                    'Create a new invoice or cancel this one first.',
                ]
            );
        }

        $validated = $request->validate([
            'issued_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'service_period_start' => ['nullable', 'date'],
            'service_period_end' => ['nullable', 'date', 'after_or_equal:service_period_start'],
            'notes' => ['nullable', 'string'],
            'footer_text' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.description' => ['required_with:items', 'string', 'max:500'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        return DB::transaction(function () use ($validated, $invoice) {
            $invoice->update(collect($validated)->except('items')->toArray());

            // Update items if provided
            if (isset($validated['items'])) {
                $existingIds = [];

                foreach ($validated['items'] as $index => $itemData) {
                    if (isset($itemData['id'])) {
                        $item = $invoice->items()->find($itemData['id']);
                        if ($item) {
                            $item->update([
                                ...$itemData,
                                'position' => $index + 1,
                            ]);
                            $existingIds[] = $item->id;
                        }
                    } else {
                        $item = $invoice->items()->create([
                            ...$itemData,
                            'vat_rate' => $itemData['vat_rate'] ?? $invoice->vat_rate,
                            'position' => $index + 1,
                        ]);
                        $existingIds[] = $item->id;
                    }
                }

                $invoice->items()->whereNotIn('id', $existingIds)->delete();
            }

            // Recalculate totals
            $invoice->refresh();
            $invoice->calculateTotals();
            $invoice->save();

            return $this->resource(
                new InvoiceResource($invoice->fresh(['client', 'items']))
            );
        });
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        // Only allow deleting draft invoices
        if ($invoice->status !== InvoiceStatus::Draft) {
            return $this->error(
                'CANNOT_DELETE_INVOICE',
                'Only draft invoices can be deleted.',
                422,
                [
                    'Current status: '.$invoice->status->value,
                    'Use PUT /invoices/{id}/cancel to cancel sent invoices.',
                ]
            );
        }

        $invoice->delete();

        return $this->success(['deleted' => true]);
    }

    public function fromProject(Request $request): JsonResponse
    {
        $request->validate([
            'project_id' => ['required', 'integer'],
        ]);

        $project = Project::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($request->project_id);

        if (! $project->canBeInvoiced()) {
            return $this->error(
                'PROJECT_CANNOT_BE_INVOICED',
                "Project in status '{$project->status->getLabel()}' cannot be invoiced.",
                422,
                [
                    'Project must be accepted, in progress, or completed.',
                    'Current status: '.$project->status->value,
                ]
            );
        }

        $settings = new SettingsService($request->user());
        $service = new InvoiceCreationService($settings);

        $invoice = $service->createFromProject($project);

        return $this->created(
            new InvoiceResource($invoice->load(['client', 'project', 'items']))
        );
    }

    public function markPaid(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        if ($invoice->status === InvoiceStatus::Paid) {
            return $this->error(
                'ALREADY_PAID',
                'Invoice is already marked as paid.',
                422
            );
        }

        if ($invoice->status === InvoiceStatus::Draft) {
            return $this->error(
                'INVOICE_NOT_SENT',
                'Cannot mark draft invoice as paid.',
                422,
                ['Invoice must be sent before marking as paid.']
            );
        }

        if ($invoice->status === InvoiceStatus::Cancelled) {
            return $this->error(
                'INVOICE_CANCELLED',
                'Cannot mark cancelled invoice as paid.',
                422
            );
        }

        $validated = $request->validate([
            'paid_at' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string', 'max:100'],
        ]);

        $invoice->markAsPaid([
            'paid_at' => $validated['paid_at'] ?? now(),
            'payment_method' => $validated['payment_method'] ?? null,
        ]);

        return $this->resource(
            new InvoiceResource($invoice->fresh(['client', 'items']))
        );
    }
}
