<?php

namespace App\Actions;

use App\DTO\NuevoProductoDTO;
use App\Models\Producto;

/**
 * Caso de uso "Registrar un producto" (el "servicio" del diagrama).
 * Recibe el DTO y lo guarda con los nombres internos de la base de datos.
 */
class RegistrarProducto
{
    public function ejecutar(NuevoProductoDTO $dto): Producto
    {
        $producto = Producto::create([
            'nombre' => $dto->nombre,
            'descripcion' => $dto->descripcion,
            'precio' => $dto->precio,
            'stock' => $dto->stock,
            'categoria_id' => $dto->categoriaId,
        ]);

        return $producto->load('categoria');
    }
}
