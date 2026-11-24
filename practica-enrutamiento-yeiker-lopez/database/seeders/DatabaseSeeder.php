<?php

namespace Database\Seeders;

use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Producto;
use Illuminate\Database\Seeder;

/** Datos FICTICIOS de la tienda para las pruebas. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $audifonos = Producto::create(['nombre' => 'Audífonos Bluetooth', 'precio' => 59.90, 'stock' => 15]);
        $mouse = Producto::create(['nombre' => 'Mouse inalámbrico', 'precio' => 19.90, 'stock' => 30]);
        $webcam = Producto::create(['nombre' => 'Webcam Full HD', 'precio' => 45.90, 'stock' => 8]);

        // [id, cliente, estado, [[producto, cantidad], ...]]
        $pedidos = [
            [1001, 'Ana Torres', 'pendiente_pago', [[$webcam, 1]]],
            [1002, 'Luis Vera', 'pagado', [[$mouse, 2]]],
            [1003, 'Carla Mora', 'pagado', [[$audifonos, 1], [$mouse, 2]]],
            [1004, 'Pedro Ruiz', 'pendiente_pago', [[$mouse, 1]]],
        ];

        foreach ($pedidos as [$id, $cliente, $estado, $items]) {
            $total = array_sum(array_map(fn ($i) => $i[0]->precio * $i[1], $items));
            $pedido = new Pedido(['cliente' => $cliente, 'total' => $total, 'estado' => $estado]);
            $pedido->id = $id;
            $pedido->save();

            foreach ($items as [$producto, $cantidad]) {
                PedidoItem::create([
                    'pedido_id' => $id,
                    'producto_id' => $producto->id,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $producto->precio,
                ]);
            }
        }
    }
}
