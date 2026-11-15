<?php

namespace Database\Seeders;

use App\Models\Producto;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Datos ficticios para probar el contrato de "Registrar un pedido".
     */
    public function run(): void
    {
        foreach ([
            ['name' => 'Cliente Web Demo', 'email' => 'cliente.web@demo.test'],
            ['name' => 'Cliente Móvil Demo', 'email' => 'cliente.movil@demo.test'],
        ] as $usuario) {
            User::factory()->create($usuario);
        }

        foreach ([
            ['nombre' => 'Audífonos Bluetooth', 'precio' => 59.90, 'stock' => 15],
            ['nombre' => 'Mouse inalámbrico', 'precio' => 18.50, 'stock' => 30],
            ['nombre' => 'Teclado mecánico', 'precio' => 45.99, 'stock' => 10],
            ['nombre' => 'Cargador USB-C 65W', 'precio' => 24.00, 'stock' => 3],
        ] as $producto) {
            Producto::create($producto);
        }
    }
}
