<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

abstract class ApiController extends Controller
{
    /**
     * Return a successful response with data.
     *
     * @param  mixed  $data
     * @param  array<string, mixed>  $meta
     */
    protected function success($data, array $meta = [], int $status = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'data' => $data,
        ];

        if (! empty($meta)) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $status);
    }

    /**
     * Return a successful response with a resource.
     *
     * @param  array<string, mixed>  $meta
     */
    protected function resource(JsonResource $resource, array $meta = [], int $status = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'data' => $resource,
        ];

        if (! empty($meta)) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $status);
    }

    /**
     * Return a paginated response.
     */
    protected function paginated(ResourceCollection $collection): JsonResponse
    {
        $paginator = $collection->resource;

        return response()->json([
            'success' => true,
            'data' => $collection->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Return an error response.
     *
     * @param  array<string>  $suggestions
     */
    protected function error(
        string $code,
        string $message,
        int $status = 400,
        array $suggestions = []
    ): JsonResponse {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if (! empty($suggestions)) {
            $error['suggestions'] = $suggestions;
        }

        return response()->json([
            'success' => false,
            'error' => $error,
        ], $status);
    }

    /**
     * Return a created response.
     *
     * @param  array<string, mixed>  $meta
     */
    protected function created(JsonResource $resource, array $meta = []): JsonResponse
    {
        return $this->resource($resource, $meta, 201);
    }

    /**
     * Return a no content response.
     */
    protected function noContent(): JsonResponse
    {
        return response()->json([
            'success' => true,
        ], 204);
    }
}
