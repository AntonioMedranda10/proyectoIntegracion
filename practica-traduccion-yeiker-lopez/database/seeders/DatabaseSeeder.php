<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Datos ficticios basados en el catálogo de ejemplo del proyecto de aula.
     */
    public function run(): void
    {
        $electronica = Categoria::create(['nombre' => 'Electrónica']);
        $hogar = Categoria::create(['nombre' => 'Hogar']);
        Categoria::create(['nombre' => 'Accesorios']);

        Producto::create([
            'nombre' => 'Audífonos Bluetooth',
            'descripcion' => 'Inalámbricos, 20 h de batería',
            'precio' => 59.90,
            'stock' => 15,
            'categoria_id' => $electronica->id,
        ]);

        Producto::create([
            'nombre' => 'Lámpara LED de escritorio',
            'descripcion' => null,
            'precio' => 24.50,
            'stock' => 0,
            'categoria_id' => $hogar->id,
        ]);
    }
}
