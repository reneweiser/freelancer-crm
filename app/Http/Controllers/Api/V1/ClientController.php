<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreClientRequest;
use App\Http\Requests\Api\V1\UpdateClientRequest;
use App\Http\Resources\Api\V1\ClientResource;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Client::query()
            ->where('user_id', $request->user()->id)
            ->withCount(['projects', 'invoices']);

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        $perPage = min($request->input('per_page', 15), 100);
        $clients = $query->orderBy('company_name')->paginate($perPage);

        return $this->paginated(ClientResource::collection($clients));
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = Client::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return $this->created(new ClientResource($client));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $client = Client::query()
            ->where('user_id', $request->user()->id)
            ->withCount(['projects', 'invoices'])
            ->findOrFail($id);

        return $this->resource(new ClientResource($client));
    }

    public function update(UpdateClientRequest $request, int $id): JsonResponse
    {
        $client = Client::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $client->update($request->validated());

        return $this->resource(new ClientResource($client->fresh()));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $client = Client::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        // Check for related records
        $projectsCount = $client->projects()->count();
        $invoicesCount = $client->invoices()->count();

        if ($projectsCount > 0 || $invoicesCount > 0) {
            return $this->error(
                'CLIENT_HAS_RELATIONS',
                'Cannot delete client with existing projects or invoices.',
                422,
                [
                    'Client has '.$projectsCount.' project(s) and '.$invoicesCount.' invoice(s).',
                    'Delete or reassign related records first.',
                    'Alternatively, use soft delete (not yet implemented via API).',
                ]
            );
        }

        $client->delete();

        return $this->success(['deleted' => true]);
    }
}
