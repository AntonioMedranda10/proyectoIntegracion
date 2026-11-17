<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Contrato de RESPUESTA de "Registrar un producto".
 *
 * SERIALIZACIÓN: el modelo interno (columnas en español) se traduce a los nombres,
 * tipos y formatos del contrato público, que son los mismos del proyecto de aula
 * (camelCase). Así el cliente web y el móvil reciben siempre la misma estructura.
 *
 * @mixin \App\Models\Producto
 */
class ProductoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->nombre,
            'description' => $this->descripcion,
            // El cast decimal:2 entrega texto ("35.50"); el contrato pide número.
            'price' => (float) $this->precio,
            'stock' => $this->stock,
            // Lo calcula el servidor: ningún cliente tiene que deducirlo por su cuenta.
            'available' => $this->stock > 0,
            'categoryId' => $this->categoria_id,
            'category' => $this->categoria ? new CategoriaResource($this->categoria) : null,
            // Ruta relativa (/files/...): cada cliente la une con su dirección base.
            'imageUrl' => $this->imagen_url,
            // ISO 8601 en UTC (termina en "Z"), igual que el resto del proyecto.
            'createdAt' => $this->created_at->toIso8601ZuluString(),
        ];
    }

    /** JSON legible: tildes y "/" sin escapar. */
    public function jsonOptions(): int
    {
        return JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    }
}
