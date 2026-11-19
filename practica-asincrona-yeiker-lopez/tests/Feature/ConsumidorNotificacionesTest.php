<?php

namespace Tests\Feature;

use App\Mensajeria\Decision;
use App\Mensajeria\ManejadorMensajes;
use App\Mensajeria\ProcesadorNotificacion;
use App\Mensajeria\PublicadorMensajes;
use App\Models\NotificacionPedido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Dobles\PublicadorEnMemoria;
use Tests\TestCase;

/**
 * CONSUMIDOR: procesa, actualiza la tabla de estados, conserva el
 * correlation_id y decide ack / nack según el resultado.
 */
class ConsumidorNotificacionesTest extends TestCase
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

        $this->archivoLog = storage_path('logs/pruebas-consumidor.log');
        @unlink($this->archivoLog);
        config(['logging.channels.mensajeria.path' => $this->archivoLog]);
    }

    /** Publica por la API (como haría Postman) y devuelve el JSON que quedaría en la cola. */
    private function publicar(int $pedidoId, string $correlationId = 'pedido-prueba-1'): string
    {
        $this->withHeader('X-Correlation-ID', $correlationId)
            ->postJson('/api/v1/notificaciones/pedidos', ['pedidoId' => $pedidoId, 'evento' => 'pedido_confirmado'])
            ->assertStatus(202);

        return end($this->publicador->publicados)->aJson();
    }

    private function manejador(): ManejadorMensajes
    {
        return $this->app->make(ManejadorMensajes::class);
    }

    public function test_procesa_el_mensaje_actualiza_el_estado_y_confirma_con_ack(): void
    {
        $cuerpo = $this->publicar(1001);

        $this->assertSame(Decision::Confirmar, $this->manejador()->manejar($cuerpo, reentregado: false));

        $registro = NotificacionPedido::sole();
        $this->assertSame('enviada', $registro->estado);
        $this->assertSame(1, $registro->intentos);
        $this->assertSame('an***@tienda.test', $registro->destinatario);
        $this->assertNotNull($registro->procesado_en);
    }

    public function test_el_mismo_correlation_id_aparece_en_productor_y_consumidor(): void
    {
        $this->manejador()->manejar($this->publicar(1002, 'pedido-1002-enviado'), false);

        $lineas = array_filter(explode("\n", file_get_contents($this->archivoLog)));
        $this->assertCount(5, $lineas); // 2 del productor + 3 del consumidor

        foreach ($lineas as $linea) {
            $this->assertStringContainsString('"correlation_id":"pedido-1002-enviado"', $linea);
        }
        $this->assertStringContainsString('productor: mensaje publicado en la cola', $lineas[1]);
        $this->assertStringContainsString('consumidor: notificación enviada; ack', $lineas[4]);
        $this->assertStringNotContainsString('ana@', file_get_contents($this->archivoLog));
    }

    public function test_un_mensaje_duplicado_no_se_envia_dos_veces(): void
    {
        $cuerpo = $this->publicar(1001);

        $this->manejador()->manejar($cuerpo, false);
        $this->assertSame(Decision::Confirmar, $this->manejador()->manejar($cuerpo, reentregado: true));

        $this->assertSame(1, NotificacionPedido::sole()->intentos);
    }

    public function test_cliente_sin_correo_es_error_permanente_y_va_a_la_cola_de_fallidas(): void
    {
        $cuerpo = $this->publicar(1004);

        $this->assertSame(Decision::EnviarAFallidas, $this->manejador()->manejar($cuerpo, false));

        $registro = NotificacionPedido::sole();
        $this->assertSame('fallida', $registro->estado);
        $this->assertSame('El cliente del pedido 1004 no tiene correo registrado.', $registro->detalle);
    }

    public function test_mensaje_que_no_cumple_el_contrato_va_a_la_cola_de_fallidas(): void
    {
        $this->assertSame(Decision::EnviarAFallidas, $this->manejador()->manejar('esto no es JSON', false));
        $this->assertSame(Decision::EnviarAFallidas, $this->manejador()->manejar('{"correlation_id":"x-1","tipo":"otro"}', false));

        $this->assertDatabaseCount('notificaciones_pedido', 0);
        $this->assertStringContainsString('consumidor: mensaje inválido', file_get_contents($this->archivoLog));
    }

    public function test_error_transitorio_se_reencola_una_vez_y_despues_va_a_fallidas(): void
    {
        $this->app->instance(ProcesadorNotificacion::class, new class extends ProcesadorNotificacion
        {
            protected function enviarCorreo(): void
            {
                throw new RuntimeException('El proveedor de correo no respondió.');
            }
        });
        $cuerpo = $this->publicar(1001);

        $this->assertSame(Decision::Reencolar, $this->manejador()->manejar($cuerpo, reentregado: false));
        $this->assertSame('en_cola', NotificacionPedido::sole()->estado);

        $this->assertSame(Decision::EnviarAFallidas, $this->manejador()->manejar($cuerpo, reentregado: true));
        $registro = NotificacionPedido::sole();
        $this->assertSame('fallida', $registro->estado);
        $this->assertSame(2, $registro->intentos);
    }
}
