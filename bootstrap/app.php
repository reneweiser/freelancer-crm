<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->redirectGuestsTo(fn () => route('filament.admin.auth.login'));
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
        $middleware->throttleApi('60,1');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'AUTHENTICATION_REQUIRED',
                        'message' => 'Authentication required.',
                        'suggestions' => [
                            'Provide a valid Bearer token in the Authorization header.',
                            'Generate a token via the API Tokens page in settings.',
                        ],
                    ],
                ], 401);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $errors = [];
                foreach ($e->errors() as $field => $messages) {
                    $errors[] = [
                        'field' => $field,
                        'messages' => $messages,
                    ];
                }

                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'The given data was invalid.',
                        'errors' => $errors,
                    ],
                ], 422);
            }
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $model = class_basename($e->getModel());

                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => "{$model} not found.",
                        'suggestions' => [
                            "Verify the {$model} ID exists.",
                            'Use GET /api/v1/'.strtolower($model).'s to list available resources.',
                        ],
                    ],
                ], 404);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                // Check if this was caused by a ModelNotFoundException
                $previous = $e->getPrevious();
                if ($previous instanceof ModelNotFoundException) {
                    $model = class_basename($previous->getModel());

                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code' => 'NOT_FOUND',
                            'message' => "{$model} not found.",
                            'suggestions' => [
                                "Verify the {$model} ID exists.",
                                'Use GET /api/v1/'.strtolower($model).'s to list available resources.',
                            ],
                        ],
                    ], 404);
                }

                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'ENDPOINT_NOT_FOUND',
                        'message' => 'The requested endpoint does not exist.',
                        'suggestions' => [
                            'Check the URL for typos.',
                            'Available endpoints: /api/v1/clients, /api/v1/projects, /api/v1/invoices, /api/v1/reminders, /api/v1/time-entries, /api/v1/recurring-tasks, /api/v1/stats',
                        ],
                    ],
                ], 404);
            }
        });
    })->create();
