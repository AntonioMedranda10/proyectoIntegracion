<?php

use App\Support\Enmascarar;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // En el despliegue, NGINX (L1) termina el TLS y reenvía la petición por la red interna.
        // Confiar en sus cabeceras X-Forwarded-* permite a Laravel saber que llegó por HTTPS.
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '127.0.0.1'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // La API responde siempre en JSON (sin esto, un 401 intentaría redirigir a una página de login).
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        // 401 · AUTENTICACIÓN: sin token, o con un token inválido, vencido o revocado.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            Log::channel('seguridad')->warning('Acceso denegado (401)', [
                'metodo' => $request->method(),
                'ruta' => $request->path(),
                'ip' => $request->ip(),
                'token' => Enmascarar::token($request->bearerToken()),
            ]);

            return response()->json(['message' => 'No autenticado: envía un token válido en la cabecera Authorization: Bearer.'], 401);
        });

        // 403 · AUTORIZACIÓN: el usuario está identificado, pero su rol o la propiedad del recurso no lo permiten.
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            Log::channel('seguridad')->warning('Acceso denegado (403)', [
                'usuario_id' => $request->user()?->id,
                'metodo' => $request->method(),
                'ruta' => $request->path(),
                'motivo' => $e->getMessage(),
            ]);

            return response()->json(['message' => $e->getMessage()], 403);
        });

        // 429 · demasiados intentos de inicio de sesión.
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            Log::channel('seguridad')->warning('Bloqueo por demasiados intentos (429)', [
                'ruta' => $request->path(),
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Demasiados intentos. Espera un minuto antes de volver a intentar.'], 429, $e->getHeaders());
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'El recurso solicitado no existe.'], 404);
            }
        });
    })->create();
