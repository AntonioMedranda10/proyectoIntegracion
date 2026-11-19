<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Pedido;
use Illuminate\Database\Seeder;

/**
 * Datos FICTICIOS para las pruebas (dominio .test, que no existe en Internet).
 * Carla no tiene correo: su pedido sirve para probar el caso de error del consumidor.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $ana = Cliente::create(['nombre' => 'Ana Torres', 'correo' => 'ana@tienda.test']);
        $luis = Cliente::create(['nombre' => 'Luis Vera', 'correo' => 'luis@tienda.test']);
        $carla = Cliente::create(['nombre' => 'Carla Mora', 'correo' => null]);

        foreach ([
            [1001, $ana, 45.50],
            [1002, $luis, 120.00],
            [1003, $ana, 18.75],
            [1004, $carla, 60.00],
        ] as [$id, $cliente, $total]) {
            $pedido = new Pedido(['cliente_id' => $cliente->id, 'total' => $total, 'estado' => 'confirmado']);
            $pedido->id = $id;
            $pedido->save();
        }
    }
}
