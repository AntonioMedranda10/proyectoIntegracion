<?php

namespace Tests\Feature;

use App\Mensajeria\Excepciones\BrokerNoDisponible;
use App\Mensajeria\RabbitMq\ConexionRabbitMq;
use App\Models\NotificacionPedido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Tests\TestCase;

/**
 * Integración con el RabbitMQ REAL (docker compose up -d).
 * Usa colas propias "*.pruebas" para no tocar las de la demostración.
 * Si el broker no está encendido, estas pruebas se omiten.
 */
class RabbitMqIntegracionTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private AbstractConnection $conexion;

    private AMQPChannel $canal;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mensajeria.exchange' => 'tienda.notificaciones.pruebas',
            'mensajeria.exchange_fallidas' => 'tienda.notificaciones.pruebas.dlx',
            'mensajeria.cola' => 'notificaciones.pedidos.pruebas',
            'mensajeria.cola_fallidas' => 'notificaciones.pedidos.pruebas.fallidas',
            'logging.channels.mensajeria.path' => storage_path('logs/pruebas-integracion.log'),
        ]);

        try {
            $this->conexion = ConexionRabbitMq::abrir('pruebas-automaticas');
        } catch (BrokerNoDisponible $e) {
            $this->markTestSkipped('RabbitMQ no está disponible: '.$e->getMessage());
        }

        $this->canal = $this->conexion->channel();
        ConexionRabbitMq::declararTopologia($this->canal);
        $this->canal->queue_purge('notificaciones.pedidos.pruebas');
        $this->canal->queue_purge('notificaciones.pedidos.pruebas.fallidas');
    }

    protected function tearDown(): void
    {
        if (isset($this->canal)) {
            $this->canal->queue_delete('notificaciones.pedidos.pruebas');
            $this->canal->queue_delete('notificaciones.pedidos.pruebas.fallidas');
            $this->canal->exchange_delete('tienda.notificaciones.pruebas');
            $this->canal->exchange_delete('tienda.notificaciones.pruebas.dlx');
            $this->canal->close();
            $this->conexion->close();
        }

        parent::tearDown();
    }

    private function tomar(string $cola): ?AMQPMessage
    {
        // El broker mueve los mensajes en milisegundos; se espera como máximo 2 s.
        for ($i = 0; $i < 20; $i++) {
            if ($mensaje = $this->canal->basic_get($cola)) {
                return $mensaje;
            }
            usleep(100_000);
        }

        return null;
    }

    public function test_el_mensaje_queda_en_la_cola_persistente_y_con_sus_identificadores(): void
    {
        $respuesta = $this->withHeader('X-Correlation-ID', 'integracion-1')
            ->postJson('/api/v1/notificaciones/pedidos', ['pedidoId' => 1001, 'evento' => 'pedido_confirmado'])
            ->assertStatus(202);

        $mensaje = $this->tomar('notificaciones.pedidos.pruebas');
        $this->assertNotNull($mensaje, 'El mensaje no llegó a la cola.');

        $this->assertSame($respuesta->json('messageId'), $mensaje->get('message_id'));
        $this->assertSame('integracion-1', $mensaje->get('correlation_id'));
        $this->assertSame(AMQPMessage::DELIVERY_MODE_PERSISTENT, $mensaje->get('delivery_mode'));
        $this->assertSame('application/json', $mensaje->get('content_type'));
        $this->assertSame('integracion-1', json_decode($mensaje->getBody(), true)['correlation_id']);

        $mensaje->ack();
    }

    public function test_nack_sin_reencolar_mueve_el_mensaje_a_la_cola_de_fallidas(): void
    {
        $id = $this->postJson('/api/v1/notificaciones/pedidos', ['pedidoId' => 1004, 'evento' => 'pedido_confirmado'])
            ->assertStatus(202)
            ->json('messageId');

        $this->tomar('notificaciones.pedidos.pruebas')->nack(requeue: false);

        $fallido = $this->tomar('notificaciones.pedidos.pruebas.fallidas');
        $this->assertNotNull($fallido, 'El mensaje no llegó a la cola de fallidas.');
        $this->assertSame($id, $fallido->get('message_id'));

        $fallido->ack();
    }

    public function test_flujo_completo_api_broker_y_comando_consumidor(): void
    {
        $id = $this->withHeader('X-Correlation-ID', 'integracion-3')
            ->postJson('/api/v1/notificaciones/pedidos', ['pedidoId' => 1002, 'evento' => 'pedido_enviado'])
            ->assertStatus(202)
            ->json('messageId');

        $this->assertSame('en_cola', NotificacionPedido::sole()->estado);

        $this->artisan('notificaciones:consumir', ['--max' => 1])
            ->expectsOutputToContain('correlation_id=integracion-3')
            ->assertSuccessful();

        $registro = NotificacionPedido::firstWhere('message_id', $id);
        $this->assertSame('enviada', $registro->estado);
        $this->assertSame('lu***@tienda.test', $registro->destinatario);

        // Se confirmó con ack: la cola quedó vacía.
        [, $pendientes] = $this->canal->queue_declare('notificaciones.pedidos.pruebas', true);
        $this->assertSame(0, $pendientes);
    }
}
