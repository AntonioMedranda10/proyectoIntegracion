<?php

namespace Tests\Feature;

use App\Models\Pedido;
use App\Models\Producto;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas del contrato de "Registrar un pedido" (POST /api/pedidos).
 */
class RegistrarPedidoTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected $seeder = DatabaseSeeder::class;

    private function solicitudValida(array $cambios = []): array
    {
        return array_replace([
            'usuario_id' => 1,
            'metodo_pago' => 'tarjeta',
            'direccion_entrega' => 'Av. 4 de Noviembre y calle 15, Manta',
            'items' => [
                ['producto_id' => 1, 'cantidad' => 2],
                ['producto_id' => 2, 'cantidad' => 1],
            ],
            'observacion' => 'Entregar en horario de la tarde',
        ], $cambios);
    }

    /** P1: JSON válido con los campos obligatorios -> 201 y respuesta según el contrato. */
    public function test_p1_json_valido_registra_el_pedido(): void
    {
        $respuesta = $this->postJson('/api/pedidos', $this->solicitudValida());

        $respuesta->assertCreated()
            ->assertHeader('Location')
            ->assertJsonStructure([
                'data' => [
                    'pedido_id', 'usuario_id', 'estado', 'metodo_pago', 'direccion_entrega',
                    'observacion', 'total', 'moneda', 'cantidad_productos', 'fecha_registro',
                    'items' => [['producto_id', 'nombre', 'cantidad', 'precio_unitario', 'subtotal']],
                ],
                'mensaje',
            ])
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.total', 138.3) // 2 x 59.90 + 1 x 18.50
            ->assertJsonPath('data.cantidad_productos', 3)
            ->assertJsonPath('data.items.0.subtotal', 119.8);

        $this->assertIsInt($respuesta->json('data.pedido_id'));
        $this->assertIsFloat($respuesta->json('data.total'));
        $this->assertSame(13, Producto::find(1)->stock); // 15 - 2
    }

    /** P2: falta un campo obligatorio -> 422 con el detalle del campo, sin guardar nada. */
    public function test_p2_falta_un_campo_obligatorio(): void
    {
        $solicitud = $this->solicitudValida();
        unset($solicitud['metodo_pago']);

        $this->postJson('/api/pedidos', $solicitud)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['metodo_pago'])
            ->assertJsonPath('errors.metodo_pago.0', 'El campo metodo_pago es obligatorio.');

        $this->assertSame(0, Pedido::count());
    }

    /** P3: tipo de dato incorrecto -> 422 controlado y no se persisten datos inválidos. */
    public function test_p3_tipo_de_dato_incorrecto(): void
    {
        $solicitud = $this->solicitudValida([
            'usuario_id' => '1', // texto en lugar de número
            'items' => [['producto_id' => 1, 'cantidad' => 'dos']],
        ]);

        $this->postJson('/api/pedidos', $solicitud)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['usuario_id', 'items.0.cantidad']);

        $this->assertSame(0, Pedido::count());
        $this->assertSame(15, Producto::find(1)->stock);
    }

    /** Extra: el cuerpo no se puede deserializar -> 400, no se confunde con campos vacíos. */
    public function test_json_mal_formado(): void
    {
        $this->call('POST', '/api/pedidos', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], '{"usuario_id": 1,')
            ->assertStatus(400)
            ->assertJsonPath('message', 'El cuerpo de la solicitud no es un JSON válido.');

        $this->assertSame(0, Pedido::count());
    }

    /** Extra: valor fuera de la lista permitida. */
    public function test_metodo_de_pago_no_permitido(): void
    {
        $this->postJson('/api/pedidos', $this->solicitudValida(['metodo_pago' => 'bitcoin']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['metodo_pago']);
    }

    /** Extra: regla de negocio. Si un producto no tiene stock, no se guarda ninguna parte del pedido. */
    public function test_stock_insuficiente_no_guarda_nada(): void
    {
        $solicitud = $this->solicitudValida([
            'items' => [
                ['producto_id' => 1, 'cantidad' => 2],
                ['producto_id' => 4, 'cantidad' => 10], // solo hay 3
            ],
        ]);

        $this->postJson('/api/pedidos', $solicitud)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.1.cantidad']);

        $this->assertSame(0, Pedido::count());
        $this->assertSame(15, Producto::find(1)->stock); // la primera línea también se revirtió
    }

    /** Extra: los campos fuera del contrato se ignoran (el cliente no puede imponer el precio). */
    public function test_campos_extra_se_ignoran(): void
    {
        $solicitud = $this->solicitudValida([
            'total' => 0.01,
            'items' => [['producto_id' => 1, 'cantidad' => 1, 'precio_unitario' => 0.01]],
        ]);

        $this->postJson('/api/pedidos', $solicitud)
            ->assertCreated()
            ->assertJsonPath('data.total', 59.9);
    }

    /** Extra: otro cliente consulta el pedido y recibe el mismo contrato. */
    public function test_consultar_pedido_devuelve_el_mismo_contrato(): void
    {
        $id = $this->postJson('/api/pedidos', $this->solicitudValida())->json('data.pedido_id');

        $this->getJson("/api/pedidos/$id")
            ->assertOk()
            ->assertJsonPath('data.pedido_id', $id)
            ->assertJsonPath('data.total', 138.3);

        $this->getJson('/api/pedidos/9999')
            ->assertNotFound()
            ->assertJsonPath('message', 'El recurso solicitado no existe.');
    }
}
