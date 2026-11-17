<?php

namespace App\Http\Controllers\Api;

use App\Actions\RegistrarProducto;
use App\DTO\NuevoProductoDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegistrarProductoRequest;
use App\Http\Resources\ProductoResource;
use App\Services\NormalizadorProductos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Recibe productos de otros orígenes. El controlador no conoce sus formatos: le pide
 * al traductor el contrato común y lo valida con las MISMAS reglas de la práctica 2.2
 * (RegistrarProductoRequest), sin modificarlas.
 */
class ImportacionController extends Controller
{
    public function __construct(private NormalizadorProductos $normalizador) {}

    /** POST /api/v1/normalize/{origen} → 200 con el contrato común | 422. No guarda nada. */
    public function normalizar(Request $request, string $origen): JsonResponse
    {
        return response()->json(
            ['source' => $origen, 'data' => $this->contratoComun($request, $origen)],
            200,
            [],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }

    /** POST /api/v1/import/{origen} → 201 con el producto (respuesta de la 2.2) | 422 */
    public function importar(Request $request, string $origen, RegistrarProducto $registrar): JsonResponse
    {
        $producto = $registrar->ejecutar(NuevoProductoDTO::desdeContrato($this->contratoComun($request, $origen)));

        return ProductoResource::make($producto)
            ->additional([
                'message' => 'Producto importado desde '.NormalizadorProductos::ORIGENES[$origen].'.',
                'source' => $origen,
            ])
            ->response()
            ->setStatusCode(201)
            ->header('Location', route('products.show', $producto));
    }

    /** Origen → traductor → contrato común, validado con las reglas de la 2.2. */
    private function contratoComun(Request $request, string $origen): array
    {
        $contrato = $this->normalizador->normalizar($origen, $request->json()->all());
        $reglasDelContrato = new RegistrarProductoRequest;

        return Validator::make($contrato, $reglasDelContrato->rules(), $reglasDelContrato->messages())->validate();
    }
}
