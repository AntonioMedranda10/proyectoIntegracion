<?php

namespace App\Http\Controllers\Api;

use App\DTO\PedidoDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegistrarPedidoRequest;
use App\Http\Resources\PedidoResource;
use App\Models\Pedido;
use App\Services\RegistrarPedidoService;
use Illuminate\Http\JsonResponse;

/**
 * Flujo: JSON -> validación (FormRequest) -> DTO -> servicio -> modelo -> Resource -> JSON.
 */
class PedidoController extends Controller
{
    /** POST /api/pedidos -> 201 Created | 422 Unprocessable Content */
    public function store(RegistrarPedidoRequest $request, RegistrarPedidoService $servicio): JsonResponse
    {
        $pedido = $servicio->ejecutar(PedidoDTO::desdeRequest($request));

        return (new PedidoResource($pedido))
            ->additional(['mensaje' => 'Pedido registrado correctamente.'])
            ->response()
            ->setStatusCode(201)
            ->header('Location', url("/api/pedidos/{$pedido->id}"));
    }

    /** GET /api/pedidos/{id} -> 200 OK | 404 Not Found. Otro cliente consume el mismo contrato. */
    public function show(Pedido $pedido): PedidoResource
    {
        return new PedidoResource($pedido->load('items'));
    }
}
