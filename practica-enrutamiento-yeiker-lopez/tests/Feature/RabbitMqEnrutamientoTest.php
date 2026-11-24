<?php

namespace Tests\Feature;

use App\Enrutamiento\Destino;
use App\Enrutamiento\Excepciones\BrokerNoDisponible;
use App\Enrutamiento\RabbitMq\ConexionRabbitMq;
use App\Models\Comprobante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use Tests\TestCase;

/**
 * Integración con el RabbitMQ REAL. Usa exchange y colas propias "*.pruebas"
 * para no tocar las de la demostración. Si el broker no está encendido, se omite.
 */
class RabbitMqEnrutamientoTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private AbstractConnection $conexion;

    private AMQPChannel $canal;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'enrutamiento.exchange' => 'tienda.eventos.pruebas',
            'enrutamiento.exchange_fallidos' => 'tienda.eventos.pruebas.dlx',
            'enrutamiento.cola_fallidos' => 'tienda.eventos.pruebas.fallidos',
            'enrutamiento.colas' => [
                'facturacion' => 'tienda.facturacion.pruebas',
                'logistica' => 'tienda.logistica.pruebas',
                'inventario' => 'tienda.inventario.pruebas',
                'no_enrutables' => 'tienda.no_enrutables.pruebas',
            ],
            'logging.channels.enrutamiento.path' => storage_path('logs/pruebas-integracion.log'),
        ]);

        try {
            $this->conexion = ConexionRabbitMq::abrir('pruebas-automaticas');
        } catch (BrokerNoDisponible $e) {
            $this->markTestSkipped('RabbitMQ no está disponible: '.$e->getMessage());
        }

        $this->canal = $this->conexion->channel();
        ConexionRabbitMq::declararTopologia($this->canal);
        foreach ([...array_values(config('enrutamiento.colas')), config('enrutamiento.cola_fallidos')] as $cola) {
            $this->canal->queue_purge($cola);
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->canal)) {
            foreach ([...array_values(config('enrutamiento.colas')), config('enrutamiento.cola_fallidos')] as $cola) {
                $this->canal->queue_delete($cola);
            }
            $this->canal->exchange_delete('tienda.eventos.pruebas');
            $this->canal->exchange_delete('tienda.eventos.pruebas.dlx');
            $this->canal->close();
            $this->conexion->close();
        }
        parent::tearDown();
    }

    private function enEspera(Destino $destino): int
    {
        [, $mensajes] = $this->canal->queue_declare($destino->cola(), true);

        return $mensajes;
    }

    public function test_cada_tipo_llega_solo_a_la_cola_de_su_destino(): void
    {
        $this->postJson('/api/v1/eventos', ['id' => 7001, 'tipo' => 'pago_aprobado', 'prioridad' => 'alta',
            'datos' => ['pedido_id' => 1001, 'monto' => 45.90]])->assertStatus(202);
        $this->postJson('/api/v1/eventos', ['id' => 7002, 'tipo' => 'pedido_despachado', 'prioridad' => 'normal',
            'datos' => ['pedido_id' => 1002, 'transportista' => 'Servientrega']])->assertStatus(202);
        $this->postJson('/api/v1/eventos', ['id' => 7003, 'tipo' => 'pedido_cancelado', 'prioridad' => 'baja',
            'datos' => ['pedido_id' => 1003, 'motivo' => 'El cliente desistió']])->assertStatus(202);
        $this->postJson('/api/v1/eventos', ['id' => 7004, 'tipo' => 'devolucion_solicitada', 'prioridad' => 'normal',
            'datos' => []])->assertStatus(202);
        $this->postJson('/api/v1/eventos', ['id' => 7005, 'tipo' => 'pago_aprobado', 'prioridad' => 'alta',
            'datos' => ['pedido_id' => 1004, 'monto' => 19.90, 'modo' => 'prueba']])->assertStatus(200); // filtrado

        $this->assertSame(1, $this->enEspera(Destino::Facturacion)); // el filtrado (7005) no llegó
        $this->assertSame(1, $this->enEspera(Destino::Logistica));
        $this->assertSame(1, $this->enEspera(Destino::Inventario));
        $this->assertSame(1, $this->enEspera(Destino::NoEnrutables));

        $mensaje = $this->canal->basic_get(Destino::Logistica->cola());
        $this->assertSame('pedido_despachado', $mensaje->get('type'));
        $this->assertSame(7002, json_decode($mensaje->getBody(), true)['id']);
        $mensaje->ack();
    }

    public function test_con_mensajes_acumulados_se_entrega_primero_la_prioridad_alta(): void
    {
        $this->postJson('/api/v1/eventos', ['id' => 7101, 'tipo' => 'pago_aprobado', 'prioridad' => 'baja',
            'datos' => ['pedido_id' => 1004, 'monto' => 19.90]])->assertStatus(202);
        $this->postJson('/api/v1/eventos', ['id' => 7102, 'tipo' => 'pago_aprobado', 'prioridad' => 'alta',
            'datos' => ['pedido_id' => 1001, 'monto' => 45.90]])->assertStatus(202);

        $primero = $this->canal->basic_get(Destino::Facturacion->cola());
        $this->assertSame(7102, json_decode($primero->getBody(), true)['id']);
        $this->assertSame(9, $primero->get('priority'));
        $primero->ack();
    }

    public function test_flujo_completo_api_broker_y_comando_consumidor(): void
    {
        $this->withHeader('X-Correlation-ID', 'integracion-pago-1001')
            ->postJson('/api/v1/eventos', ['id' => 7201, 'tipo' => 'pago_aprobado', 'prioridad' => 'alta',
                'datos' => ['pedido_id' => 1001, 'monto' => 45.90]])->assertStatus(202);

        $this->artisan('eventos:consumir', ['destino' => 'facturacion', '--max' => 1])
            ->expectsOutputToContain('correlation=integracion-pago-1001 → ack · Comprobante FAC-000001')
            ->assertSuccessful();

        $this->assertSame(1, Comprobante::count());
        $this->assertSame(0, $this->enEspera(Destino::Facturacion));
    }
}
