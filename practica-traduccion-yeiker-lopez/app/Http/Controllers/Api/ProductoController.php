<?php

namespace App\Http\Controllers\Api;

use App\Actions\RegistrarProducto;
use App\DTO\NuevoProductoDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegistrarProductoRequest;
use App\Http\Resources\ProductoResource;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;

/**
 * JSON → RegistrarProductoRequest (valida) → NuevoProductoDTO (deserializa)
 *      → RegistrarProducto (guarda) → ProductoResource (serializa) → JSON
 */
class ProductoController extends Controller
{
    /** POST /api/v1/products → 201 Created | 422 Unprocessable Content */
    public function store(RegistrarProductoRequest $request, RegistrarProducto $registrar): JsonResponse
    {
        $producto = $registrar->ejecutar(NuevoProductoDTO::desdeContrato($request->validated()));

        return ProductoResource::make($producto)
            ->additional(['message' => 'Producto registrado correctamente.'])
            ->response()
            ->setStatusCode(201)
            ->header('Location', route('products.show', $producto));
    }

    /** GET /api/v1/products/{id} → 200 OK | 404 Not Found */
    public function show(Producto $producto): ProductoResource
    {
        return ProductoResource::make($producto->load('categoria'));
    }
}
