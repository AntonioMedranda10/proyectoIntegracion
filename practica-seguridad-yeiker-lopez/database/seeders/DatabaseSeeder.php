<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Usuarios y mensajes ficticios (dominio .test, reservado para pruebas).
     * Las contraseñas salen del .env; si faltan, el seeder se detiene.
     */
    public function run(): void
    {
        $claveAdmin = config('seguridad.clave_admin');
        $claveCliente = config('seguridad.clave_cliente');

        if (blank($claveAdmin) || blank($claveCliente)) {
            throw new RuntimeException('Define SEED_ADMIN_PASSWORD y SEED_CLIENTE_PASSWORD en tu .env antes de ejecutar el seeder.');
        }

        User::forceCreate(['name' => 'Administrador Tienda', 'email' => 'admin@tienda.test', 'password' => $claveAdmin, 'role' => User::ROL_ADMIN]);
        $ana = User::forceCreate(['name' => 'Ana Pérez', 'email' => 'ana@tienda.test', 'password' => $claveCliente, 'role' => User::ROL_CLIENTE]);
        $luis = User::forceCreate(['name' => 'Luis Mora', 'email' => 'luis@tienda.test', 'password' => $claveCliente, 'role' => User::ROL_CLIENTE]);

        $ana->mensajes()->create(['subject' => 'Estado de mi pedido', 'content' => '¿Cuándo llega mi pedido #3?']);
        $ana->mensajes()->create(['subject' => 'Factura', 'content' => 'Necesito la factura del pedido #3.']);
        $luis->mensajes()->create(['subject' => 'Garantía', 'content' => 'Mis audífonos dejaron de cargar, ¿aplica la garantía?']);
    }
}
