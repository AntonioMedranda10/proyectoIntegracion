<?php

namespace Tests\Feature;

use App\Enrutamiento\PublicadorEventos;
use App\Models\EventoRecibido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Dobles\PublicadorEnMemoria;
use Tests\TestCase;

/**
 * Los seis casos obligatorios de la guía por HTTP:
 * validación → filtro → enrutador → (transporte en memoria).
 */
class CanalDeEntradaTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private PublicadorEnMemoria $transporte;

    private string $archivoLog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transporte = new PublicadorEnMemoria;
        $this->app->instance(PublicadorEventos::class, $this->transporte);
        $this->archivoLog = storage_path('logs/pruebas-entrada.log');
        @unlink($this->archivoLog);
        config(['logging.channels.enrutamiento.path' => $this->archivoLog]);
    }

    private function pago(int $id = 5001, array $extra = []): array
    {
        return ['id' => $id, 'tipo' => 'pago_aprobado', 'prioridad' => 'alta',
            'datos' => array_merge(['pedido_id' => 1001, 'monto' => 45.90, 'metodo' => 'tarjeta', 'modo' => 'real'], $extra)];
    }

    public function test_casos_1_a_3_cada_tipo_llega_solo_a_su_consumidor(): void
    {
        $this->postJson('/api/v1/eventos', $this->pago(5001))
            ->assertStatus(202)
            ->assertJsonPath('decision', 'enrutado')
            ->assertJsonPath('regla', 'R1')
            ->assertJsonPath('cola', 'tienda.facturacion');

        $this->postJson('/api/v1/eventos', ['id' => 5002, 'tipo' => 'pedido_despachado', 'prioridad' => 'normal',
            'datos' => ['pedido_id' => 1002, 'transportista' => 'Servientrega']])
            ->assertStatus(202)
            ->assertJsonPath('regla', 'R2')
            ->assertJsonPath('cola', 'tienda.logistica');

        $this->postJson('/api/v1/eventos', ['id' => 5003, 'tipo' => 'pedido_cancelado', 'prioridad' => 'baja',
            'datos' => ['pedido_id' => 1003, 'motivo' => 'El cliente desistió']])
            ->assertStatus(202)
            ->assertJsonPath('regla', 'R3')
            ->assertJsonPath('cola', 'tienda.inventario');

        // Cada evento se publicó UNA sola vez y en su destino.
        $this->assertSame([[5001, 'facturacion'], [5002, 'logistica'], [5003, 'inventario']], $this->transporte->destinos());
        $this->assertSame(3, EventoRecibido::where('decision', 'enrutado')->where('estado_consumo', 'en_cola')->count());
    }

    public function test_caso_4_tipo_desconocido_va_a_no_enrutables_sin_descartarse(): void
    {
        $this->postJson('/api/v1/eventos', ['id' => 5004, 'tipo' => 'devolucion_solicitada', 'prioridad' => 'normal',
            'datos' => ['pedido_id' => 1002, 'comentario' => 'Producto con falla']])
            ->assertStatus(202)
            ->assertJsonPath('decision', 'no_enrutable')
            ->assertJsonPath('regla', 'R4')
            ->assertJsonPath('cola', 'tienda.no_enrutables');

        $this->assertSame([[5004, 'no_enrutables']], $this->transporte->destinos());
        // El contenido completo llega a la revisión, aunque sus campos no tengan reglas.
        $this->assertSame('Producto con falla', $this->transporte->publicados[0][1]->datos['comentario']);
        $this->assertDatabaseHas('eventos_recibidos', ['evento_id' => 5004, 'decision' => 'no_enrutable', 'estado_consumo' => 'en_revision']);
    }

    public function test_caso_5_evento_que_no_cumple_el_filtro_no_llega_a_ningun_consumidor(): void
    {
        $this->postJson('/api/v1/eventos', $this->pago(5005, ['modo' => 'prueba']))
            ->assertStatus(200)
            ->assertJsonPath('decision', 'filtrado')
            ->assertJsonPath('regla', 'F1');

        $this->assertCount(0, $this->transporte->publicados);
        $this->assertDatabaseHas('eventos_recibidos', ['evento_id' => 5005, 'decision' => 'filtrado', 'regla' => 'F1', 'destino' => null]);
        $this->assertStringContainsString('filtro: rechazado por regla de negocio', file_get_contents($this->archivoLog));
    }

    public function test_un_evento_duplicado_se_filtra_y_no_se_publica_dos_veces(): void
    {
        $this->postJson('/api/v1/eventos', $this->pago(5001))->assertStatus(202);
        $this->postJson('/api/v1/eventos', $this->pago(5001))
            ->assertStatus(200)
            ->assertJsonPath('decision', 'filtrado')
            ->assertJsonPath('regla', 'F2');

        $this->assertCount(1, $this->transporte->publicados);
        $this->getJson('/api/v1/eventos/5001')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.decision', 'enrutado')
            ->assertJsonPath('data.1.decision', 'filtrado');
    }

    public function test_caso_6_mensaje_sin_campos_obligatorios_se_rechaza_de_forma_controlada(): void
    {
        $respuesta = $this->postJson('/api/v1/eventos', ['id' => 5006, 'tipo' => 'pago_aprobado', 'datos' => ['pedido_id' => 1001]])
            ->assertStatus(422)
            ->assertJsonPath('message', 'El evento no cumple el contrato: no pasó al filtro ni al enrutador.')
            ->assertJsonPath('errors.prioridad.0', 'La prioridad es obligatoria.');
        $this->assertSame('Un pago_aprobado necesita datos.monto.', $respuesta->json('errors')['datos.monto'][0]);

        $this->postJson('/api/v1/eventos', ['tipo' => 'pago_aprobado'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id', 'prioridad', 'datos']);

        $this->assertCount(0, $this->transporte->publicados);
        $this->assertDatabaseCount('eventos_recibidos', 0);
        $this->assertStringContainsString('validacion: evento rechazado por el contrato', file_get_contents($this->archivoLog));
    }

    public function test_tipo_conocido_con_pedido_inexistente_o_id_como_texto_es_422(): void
    {
        $respuesta = $this->postJson('/api/v1/eventos', $this->pago(5007, ['pedido_id' => 9999]))->assertStatus(422);
        $this->assertSame('El pedido indicado en datos.pedido_id no existe.', $respuesta->json('errors')['datos.pedido_id'][0]);

        $this->postJson('/api/v1/eventos', ['id' => '5008'] + $this->pago())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id']);
    }

    public function test_fallo_de_transporte_es_503_y_no_se_confunde_con_el_filtrado(): void
    {
        $this->transporte->brokerCaido = true;

        $this->postJson('/api/v1/eventos', $this->pago(5009))
            ->assertStatus(503)
            ->assertJsonPath('decision', 'error_transporte')
            ->assertJsonPath('destinoPrevisto', 'facturacion');

        $this->assertDatabaseHas('eventos_recibidos', ['evento_id' => 5009, 'decision' => 'error_transporte']);
        $log = file_get_contents($this->archivoLog);
        $this->assertStringContainsString('ERROR: transporte: el broker no confirmó', $log);
        $this->assertStringNotContainsString('filtro: rechazado', $log);

        // Al volver el broker, el mismo id se puede reenviar (F2 no lo bloquea).
        $this->transporte->brokerCaido = false;
        $this->postJson('/api/v1/eventos', $this->pago(5009))->assertStatus(202);
    }

    public function test_el_correlation_id_acompana_todas_las_decisiones(): void
    {
        $this->withHeader('X-Correlation-ID', 'pedido-1001-pago')
            ->postJson('/api/v1/eventos', $this->pago(5010))
            ->assertHeader('X-Correlation-ID', 'pedido-1001-pago')
            ->assertJsonPath('correlationId', 'pedido-1001-pago');

        $this->assertSame('pedido-1001-pago', $this->transporte->publicados[0][1]->correlationId);
        $lineas = array_filter(explode("\n", file_get_contents($this->archivoLog)));
        $this->assertCount(4, $lineas); // validación, filtro, enrutador y transporte
        foreach ($lineas as $linea) {
            $this->assertStringContainsString('"correlation_id":"pedido-1001-pago"', $linea);
        }
    }

    public function test_la_tabla_de_reglas_expone_las_mismas_reglas_del_codigo(): void
    {
        $this->getJson('/api/v1/reglas')
            ->assertOk()
            ->assertJsonCount(2, 'filtro')
            ->assertJsonCount(4, 'enrutador')
            ->assertJsonPath('enrutador.0.condicion', 'tipo = pago_aprobado')
            ->assertJsonPath('enrutador.3.destino', 'no_enrutables');
    }
}
