<?php

namespace Tests\Feature;

use App\Enrutamiento\Consumidores\ConsumidorFacturacion;
use App\Enrutamiento\Consumidores\ConsumidorInventario;
use App\Enrutamiento\Consumidores\ConsumidorLogistica;
use App\Enrutamiento\Consumidores\Decision;
use App\Enrutamiento\Consumidores\ManejadorDeConsumo;
use App\Enrutamiento\PublicadorEventos;
use App\Models\Comprobante;
use App\Models\Envio;
use App\Models\EventoRecibido;
use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Dobles\PublicadorEnMemoria;
use Tests\TestCase;

/** Cada consumidor hace su trabajo real y SOLO acepta sus tipos de evento. */
class ConsumidoresTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private PublicadorEnMemoria $transporte;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transporte = new PublicadorEnMemoria;
        $this->app->instance(PublicadorEventos::class, $this->transporte);
        config(['logging.channels.enrutamiento.path' => storage_path('logs/pruebas-consumidores.log')]);
    }

    /** Envía el evento por la API y devuelve el JSON que quedaría en la cola. */
    private function enrutar(array $evento): string
    {
        $this->postJson('/api/v1/eventos', $evento)->assertStatus(202);

        return end($this->transporte->publicados)[1]->aJson();
    }

    private function manejar(string $claseConsumidor, string $cuerpo, bool $reentregado = false)
    {
        return app(ManejadorDeConsumo::class)->manejar(app($claseConsumidor), $cuerpo, $reentregado);
    }

    public function test_facturacion_emite_un_comprobante_y_actualiza_el_recorrido(): void
    {
        $cuerpo = $this->enrutar(['id' => 6001, 'tipo' => 'pago_aprobado', 'prioridad' => 'alta',
            'datos' => ['pedido_id' => 1001, 'monto' => 45.90, 'metodo' => 'tarjeta']]);

        $resultado = $this->manejar(ConsumidorFacturacion::class, $cuerpo);

        $this->assertSame(Decision::Confirmar, $resultado->decision);
        $this->assertSame('Comprobante FAC-000001 emitido por $45.90 (pedido 1001)', $resultado->detalle);
        $this->assertDatabaseHas('comprobantes', ['numero' => 'FAC-000001', 'pedido_id' => 1001, 'evento_id' => 6001]);
        $this->assertDatabaseHas('eventos_recibidos', ['evento_id' => 6001, 'estado_consumo' => 'procesado', 'consumidor' => 'facturacion']);
    }

    public function test_logistica_registra_una_guia(): void
    {
        $cuerpo = $this->enrutar(['id' => 6002, 'tipo' => 'pedido_despachado', 'prioridad' => 'normal',
            'datos' => ['pedido_id' => 1002, 'transportista' => 'Servientrega']]);

        $resultado = $this->manejar(ConsumidorLogistica::class, $cuerpo);

        $this->assertSame(Decision::Confirmar, $resultado->decision);
        $this->assertMatchesRegularExpression('/^Guía GUIA-1002-[A-Z0-9]{4} registrada con Servientrega/', $resultado->detalle);
        $this->assertSame(1, Envio::where('pedido_id', 1002)->count());
    }

    public function test_inventario_devuelve_el_stock_una_sola_vez(): void
    {
        $cuerpo = $this->enrutar(['id' => 6003, 'tipo' => 'pedido_cancelado', 'prioridad' => 'baja',
            'datos' => ['pedido_id' => 1003, 'motivo' => 'El cliente desistió']]);

        $this->manejar(ConsumidorInventario::class, $cuerpo);
        $this->assertSame(16, Producto::find(1)->stock); // Audífonos 15 + 1
        $this->assertSame(32, Producto::find(2)->stock); // Mouse 30 + 2

        // Si RabbitMQ lo entregara otra vez, no se devuelve el stock dos veces.
        $segundo = $this->manejar(ConsumidorInventario::class, $cuerpo, reentregado: true);
        $this->assertSame(Decision::Confirmar, $segundo->decision);
        $this->assertStringContainsString('ya se había devuelto', $segundo->detalle);
        $this->assertSame(16, Producto::find(1)->stock);
    }

    public function test_un_consumidor_rechaza_un_evento_que_no_le_corresponde(): void
    {
        $cuerpo = $this->enrutar(['id' => 6004, 'tipo' => 'pago_aprobado', 'prioridad' => 'alta',
            'datos' => ['pedido_id' => 1001, 'monto' => 45.90]]);

        // Si por error un pago llegara a Inventario, no debe tocar el stock.
        $resultado = $this->manejar(ConsumidorInventario::class, $cuerpo);

        $this->assertSame(Decision::EnviarAFallidos, $resultado->decision);
        $this->assertSame(15, Producto::find(1)->stock);
        $this->assertSame(0, Comprobante::count());
        $this->assertDatabaseHas('eventos_recibidos', ['evento_id' => 6004, 'estado_consumo' => 'fallido']);
    }

    public function test_un_mensaje_fuera_de_contrato_va_a_fallidos(): void
    {
        $this->assertSame(Decision::EnviarAFallidos, $this->manejar(ConsumidorFacturacion::class, 'no es JSON')->decision);
        $this->assertSame(Decision::EnviarAFallidos, $this->manejar(ConsumidorFacturacion::class, '{"id":"x"}')->decision);
        $this->assertSame(0, EventoRecibido::count());
    }
}
