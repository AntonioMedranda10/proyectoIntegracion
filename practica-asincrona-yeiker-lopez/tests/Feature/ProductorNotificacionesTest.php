<?php

namespace Tests\Feature;

use App\Mensajeria\PublicadorMensajes;
use App\Models\NotificacionPedido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Dobles\PublicadorEnMemoria;
use Tests\TestCase;

/**
 * PRODUCTOR (API Laravel): valida, genera o preserva el correlation_id,
 * publica el mensaje y responde 202 sin afirmar que el trabajo terminó.
 */
class ProductorNotificacionesTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private PublicadorEnMemoria $publicador;

    private string $archivoLog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->publicador = new PublicadorEnMemoria;
        $this->app->instance(PublicadorMensajes::class, $this->publicador);

        $this->archivoLog = storage_path('logs/pruebas-productor.log');
        @unlink($this->archivoLog);
        config(['logging.channels.mensajeria.path' => $this->archivoLog]);
    }

    public function test_mensaje_valido_responde_202_y_publica_el_contrato_completo(): void
    {
        $respuesta = $this->withHeader('X-Correlation-ID', 'pedido-1001-confirmacion')
            ->postJson('/api/v1/notificaciones/pedidos', ['pedidoId' => 1001, 'evento' => 'pedido_confirmado']);

        $respuesta->assertStatus(202)
            ->assertHeader('X-Correlation-ID', 'pedido-1001-confirmacion')
            ->assertJsonPath('estado', 'en_cola')
            ->assertJsonPath('correlationId', 'pedido-1001-confirmacion')
            ->assertJsonPath('cola', 'notificaciones.pedidos');
        $this->assertStringContainsString('/api/v1/notificaciones/', $respuesta->headers->get('Location'));

        $this->assertCount(1, $this->publicador->publicados);
        $mensaje = $this->publicador->publicados[0]->aArreglo();

        $this->assertSame(['message_id', 'correlation_id', 'tipo', 'ocurrido_en', 'payload'], array_keys($mensaje));
        $this->assertTrue(Str::isUuid($mensaje['message_id']));
        $this->assertSame($respuesta->json('messageId'), $mensaje['message_id']);
        $this->assertSame('pedido-1001-confirmacion', $mensaje['correlation_id']);
        $this->assertSame('notificacion_pedido', $mensaje['tipo']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/', $mensaje['ocurrido_en']);
        $this->assertSame(['pedido_id' => 1001, 'cliente_id' => 1, 'evento' => 'pedido_confirmado', 'canal' => 'correo'], $mensaje['payload']);

        // Sin datos sensibles innecesarios: el correo del cliente no viaja en el mensaje.
        $this->assertStringNotContainsString('@', $this->publicador->publicados[0]->aJson());

        $this->assertDatabaseHas('notificaciones_pedido', [
            'message_id' => $mensaje['message_id'],
            'correlation_id' => 'pedido-1001-confirmacion',
            'modo' => 'asincrono',
            'estado' => 'en_cola',
        ]);
    }

    public function test_genera_un_correlation_id_si_el_cliente_no_lo_envia(): void
    {
        $respuesta = $this->postJson('/api/v1/notificaciones/pedidos', ['pedidoId' => 1002, 'evento' => 'pedido_enviado'])
            ->assertStatus(202);

        $generado = $respuesta->json('correlationId');
        $this->assertTrue(Str::isUuid($generado));
        $respuesta->assertHeader('X-Correlation-ID', $generado);
        $this->assertSame($generado, $this->publicador->publicados[0]->correlationId);
    }

    public function test_correlation_id_con_caracteres_no_permitidos_se_reemplaza(): void
    {
        $respuesta = $this->withHeader('X-Correlation-ID', 'abc<script>')
            ->postJson('/api/v1/notificaciones/pedidos', ['pedidoId' => 1002, 'evento' => 'pedido_enviado'])
            ->assertStatus(202);

        $this->assertTrue(Str::isUuid($respuesta->json('correlationId')));
    }

    public function test_mensaje_invalido_responde_422_y_no_publica_nada(): void
    {
        $this->postJson('/api/v1/notificaciones/pedidos', ['evento' => 'pedido_perdido'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Los datos enviados no son válidos. No se publicó ningún mensaje.')
            ->assertJsonPath('errors.pedidoId.0', 'El pedido (pedidoId) es obligatorio.')
            ->assertJsonPath('errors.evento.0', 'El evento debe ser uno de: pedido_confirmado, pedido_enviado, pedido_entregado.');

        $this->postJson('/api/v1/notificaciones/pedidos', ['pedidoId' => '1001', 'evento' => 'pedido_confirmado'])
            ->assertStatus(422)
            ->assertJsonPath('errors.pedidoId.0', 'El pedido (pedidoId) debe ser un número entero JSON, no texto. Ejemplo: 1001');

        $this->assertCount(0, $this->publicador->publicados);
        $this->assertDatabaseCount('notificaciones_pedido', 0);
    }

    public function test_pedido_inexistente_responde_422(): void
    {
        $this->postJson('/api/v1/notificaciones/pedidos', ['pedidoId' => 9999, 'evento' => 'pedido_confirmado'])
            ->assertStatus(422)
            ->assertJsonPath('errors.pedidoId.0', 'El pedido indicado no existe.');

        $this->assertCount(0, $this->publicador->publicados);
    }

    public function test_broker_caido_responde_503_y_no_afirma_que_quedo_en_cola(): void
    {
        $this->publicador->brokerCaido = true;

        $respuesta = $this->postJson('/api/v1/notificaciones/pedidos', ['pedidoId' => 1001, 'evento' => 'pedido_confirmado'])
            ->assertStatus(503)
            ->assertJsonPath('estado', 'no_publicada');

        $this->assertDatabaseHas('notificaciones_pedido', [
            'message_id' => $respuesta->json('messageId'),
            'estado' => 'no_publicada',
        ]);
    }

    public function test_version_sincrona_responde_200_cuando_el_envio_ya_termino(): void
    {
        $this->postJson('/api/v1/notificaciones/pedidos/sincrono', ['pedidoId' => 1001, 'evento' => 'pedido_confirmado'])
            ->assertStatus(200)
            ->assertJsonPath('modo', 'sincrono')
            ->assertJsonPath('estado', 'enviada')
            ->assertJsonPath('intentos', 1)
            ->assertJsonPath('destinatario', 'an***@tienda.test');

        // La versión síncrona no usa la cola.
        $this->assertCount(0, $this->publicador->publicados);
    }

    public function test_version_sincrona_con_cliente_sin_correo_responde_422(): void
    {
        $this->postJson('/api/v1/notificaciones/pedidos/sincrono', ['pedidoId' => 1004, 'evento' => 'pedido_confirmado'])
            ->assertStatus(422)
            ->assertJsonPath('estado', 'fallida')
            ->assertJsonPath('error', 'El cliente del pedido 1004 no tiene correo registrado.');
    }

    public function test_consulta_la_tabla_de_estados_por_message_id_y_por_correlation_id(): void
    {
        $id = $this->withHeader('X-Correlation-ID', 'operacion-77')
            ->postJson('/api/v1/notificaciones/pedidos', ['pedidoId' => 1003, 'evento' => 'pedido_entregado'])
            ->json('messageId');

        $this->getJson("/api/v1/notificaciones/{$id}")
            ->assertOk()
            ->assertJsonPath('estado', 'en_cola')
            ->assertJsonPath('correlationId', 'operacion-77')
            ->assertJsonPath('procesadoEn', null);

        $this->getJson('/api/v1/notificaciones?correlationId=operacion-77')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.messageId', $id);

        $this->getJson('/api/v1/notificaciones/'.Str::uuid())->assertNotFound();
    }

    public function test_el_productor_registra_el_correlation_id_en_el_log(): void
    {
        $this->withHeader('X-Correlation-ID', 'pedido-1001-confirmacion')
            ->postJson('/api/v1/notificaciones/pedidos', ['pedidoId' => 1001, 'evento' => 'pedido_confirmado'])
            ->assertStatus(202);

        $log = file_get_contents($this->archivoLog);
        $this->assertStringContainsString('productor: solicitud recibida y validada', $log);
        $this->assertStringContainsString('productor: mensaje publicado en la cola', $log);
        $this->assertSame(2, substr_count($log, '"correlation_id":"pedido-1001-confirmacion"'));
        $this->assertSame(1, NotificacionPedido::count());
    }
}
