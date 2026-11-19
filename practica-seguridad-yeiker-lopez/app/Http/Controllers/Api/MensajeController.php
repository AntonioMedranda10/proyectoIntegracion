<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MensajeResource;
use App\Models\Mensaje;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Todas las acciones llegan con el usuario ya autenticado (middleware auth:sanctum).
 * La autorización por rol o propietario la decide MensajePolicy.
 */
class MensajeController extends Controller
{
    /** GET /api/v1/messages → cliente: solo sus mensajes · administrador: todos */
    public function index(Request $request): AnonymousResourceCollection
    {
        $usuario = $request->user();
        $consulta = $usuario->esAdministrador() ? Mensaje::query() : $usuario->mensajes();

        return MensajeResource::collection($consulta->with('autor')->orderBy('id')->get());
    }

    /** GET /api/v1/messages/{id} → 200 | 403 si el mensaje es de otro cliente */
    public function show(Mensaje $mensaje): MensajeResource
    {
        Gate::authorize('view', $mensaje);

        return MensajeResource::make($mensaje->load('autor'));
    }

    /** POST /api/v1/messages → 201. El autor sale del token, nunca del cuerpo de la petición. */
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'content' => ['required', 'string', 'max:2000'],
        ]);

        $mensaje = $request->user()->mensajes()->create($datos);

        return MensajeResource::make($mensaje->load('autor'))->response()->setStatusCode(201);
    }

    /** DELETE /api/v1/messages/{id} → 200 | 403 si no es administrador */
    public function destroy(Mensaje $mensaje): JsonResponse
    {
        Gate::authorize('delete', $mensaje);

        $mensaje->delete();

        return response()->json(['message' => 'Mensaje eliminado.']);
    }
}
