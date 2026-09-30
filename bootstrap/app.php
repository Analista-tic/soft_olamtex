<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Middleware\HandleCors;
use Inertia\Inertia;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance']);

        // CORS debe ejecutarse antes que otros middlewares web
        $middleware->prepend(HandleCors::class);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\PreventDuplicateSubmission::class,
        ]);

        // Alias para middlewares de roles y permisos
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Última defensa contra duplicados: si dos peticiones simultáneas pasan la validación y chocan con un
        // índice único, se responde 422 con un mensaje claro en vez de un 500.
        $exceptions->render(function (\Illuminate\Database\UniqueConstraintViolationException $exception, $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            $message = (string) $exception->getMessage();
            [$field, $text] = match (true) {
                str_contains($message, 'supplier_invoice') => ['supplier_invoice_number', 'Este proveedor ya tiene registrada una factura con ese número.'],
                str_contains($message, 'document_number'), str_contains($message, 'request_number'), str_contains($message, 'purchase_documents_type_number') => ['document_number', 'El consecutivo ya fue asignado a otro documento. Vuelve a guardar para obtener uno nuevo.'],
                str_contains($message, 'roll_number') => ['roll_number', 'Ya existe un rollo con ese número.'],
                default => ['general', 'Ya existe un registro con esos datos. Actualiza la lista antes de volver a intentarlo.'],
            };

            return response()->json(['message' => $text, 'errors' => [$field => [$text]]], 422);
        });

        $exceptions->render(function (AuthenticationException $exception, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'No autenticado.',
                ], 401);
            }

            if ($request->header('X-Inertia')) {
                return Inertia::location(route('login'));
            }

            return redirect()->guest(route('login'));
        });
    })->create();
