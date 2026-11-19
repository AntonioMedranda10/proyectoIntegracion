<?php

use App\Http\Middleware\CorrelationId;
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
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Genera o propaga X-Correlation-ID en todas las rutas /api.
        $middleware->api(append: [CorrelationId::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // La API responde siempre en JSON, aunque el cliente no envíe "Accept: application/json".
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        // 422 con un mensaje general en español y el detalle por campo en "errors".
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Los datos enviados no son válidos. No se publicó ningún mensaje.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        // 404 en JSON, sin exponer clases internas.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['error' => 'El recurso solicitado no existe.'], 404);
            }
        });
    })->create();
