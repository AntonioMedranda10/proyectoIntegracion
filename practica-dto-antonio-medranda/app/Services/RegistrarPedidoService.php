<?php

namespace App\Services;

use App\DTO\PedidoDTO;
use App\Models\Pedido;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Regla de negocio de "Registrar un pedido".
 *
 * Recibe el DTO (nunca el JSON crudo). Los precios se toman de la base de datos,
 * no del cliente, para que nadie pueda enviar un precio manipulado.
 */
class RegistrarPedidoService
{
    public function ejecutar(PedidoDTO $dto): Pedido
    {
        // Todo o nada: si un producto no tiene stock, no se guarda ninguna parte del pedido.
        return DB::transaction(function () use ($dto) {
            $lineas = [];
            $total = 0.0;

            foreach ($dto->items as $indice => $item) {
                $producto = Producto::lockForUpdate()->findOrFail($item->productoId);

                if ($producto->stock < $item->cantidad) {
                    throw ValidationException::withMessages([
                        "items.$indice.cantidad" => "Stock insuficiente para \"{$producto->nombre}\": disponible {$producto->stock}.",
                    ]);
                }

                $precio = (float) $producto->precio;
                $subtotal = round($precio * $item->cantidad, 2);
                $total += $subtotal;

                $producto->decrement('stock', $item->cantidad);

                $lineas[] = [
                    'producto_id' => $producto->id,
                    'nombre_producto' => $producto->nombre,
                    'precio_unitario' => $precio,
                    'cantidad' => $item->cantidad,
                    'subtotal' => $subtotal,
                ];
            }

            $pedido = Pedido::create([
                'usuario_id' => $dto->usuarioId,
                'metodo_pago' => $dto->metodoPago,
                'direccion_entrega' => $dto->direccionEntrega,
                'observacion' => $dto->observacion,
                'estado' => 'pendiente',
                'total' => round($total, 2),
            ]);
            $pedido->items()->createMany($lineas);

            return $pedido->load('items');
        });
    }
}
