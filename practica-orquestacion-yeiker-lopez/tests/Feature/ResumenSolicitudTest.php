<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request as PeticionSaliente;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Orquestación de GET /api/resumen-solicitud/{id}. Los servicios se simulan con
 * Http::fake, así cada tipo de falla se prueba sin levantar servidores.
 */
class ResumenSolicitudTest extends TestCase
{
    private const URL = '/api/resumen-solicitud/101';

    private string $registro;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests(); // ninguna llamada real sale durante las pruebas

        // El registro de la orquestación va a un archivo propio de las pruebas.
        $this->registro = storage_path('logs/orquestacion-pruebas.log');
        @unlink($this->registro);
        config(['logging.channels.orquestacion.path' => $this->registro]);
    }

    protected function tearDown(): void
    {
        @unlink($this->registro);
        parent::tearDown();
    }

    private function simularServicios(mixed $usuarios = null, mixed $estados = null): void
    {
        Http::fake([
            '*/usuarios/7' => $usuarios ?? Http::response(['id' => 7, 'nombre' => 'Ana']),
            '*/estados/101' => $estados ?? Http::response(['solicitud_id' => 101, 'estado' => 'pendiente']),
        ]);
    }

    private function registro(): string
    {
        return file_exists($this->registro) ? file_get_contents($this->registro) : '';
    }

    public function test_compone_un_solo_json_y_propaga_el_correlation_id(): void
    {
        $this->simularServicios();

        $this->withHeader('X-Correlation-ID', 'clase-101')->getJson(self::URL)
            ->assertOk()
            ->assertExactJson([
                'solicitud_id' => 101,
                'usuario' => ['id' => 7, 'nombre' => 'Ana'],
                'estado' => 'pendiente',
                'correlation_id' => 'clase-101',
            ])
            ->assertHeader('X-Correlation-ID', 'clase-101');

        // Se consultó a los dos servicios, en orden, y ambos recibieron el mismo identificador.
        Http::assertSentCount(2);
        Http::assertSentInOrder([
            fn (PeticionSaliente $r) => str_ends_with($r->url(), '/usuarios/7') && $r->hasHeader('X-Correlation-ID', 'clase-101'),
            fn (PeticionSaliente $r) => str_ends_with($r->url(), '/estados/101') && $r->hasHeader('X-Correlation-ID', 'clase-101'),
        ]);
        $this->assertStringContainsString('Orquestación completada', $this->registro());
        $this->assertStringContainsString('"correlation_id":"clase-101"', $this->registro());
    }

    public function test_genera_un_correlation_id_si_el_cliente_no_envia_uno(): void
    {
        $this->simularServicios();

        $respuesta = $this->getJson(self::URL)->assertOk();

        $generado = $respuesta->json('correlation_id');
        $this->assertTrue(Str::isUuid($generado));
        $respuesta->assertHeader('X-Correlation-ID', $generado);
        Http::assertSent(fn (PeticionSaliente $r) => $r->hasHeader('X-Correlation-ID', $generado));
    }

    public function test_reemplaza_un_correlation_id_invalido(): void
    {
        $this->simularServicios();

        $respuesta = $this->withHeader('X-Correlation-ID', 'con espacios y <script>')->getJson(self::URL)->assertOk();

        $this->assertTrue(Str::isUuid($respuesta->json('correlation_id')));
    }

    public function test_si_estados_responde_un_error_http_devuelve_502_controlado(): void
    {
        $this->simularServicios(estados: Http::response(['error' => 'Fallo interno'], 500));

        // assertExactJson: solo estos tres campos, sin trazas ni direcciones internas.
        $this->withHeader('X-Correlation-ID', 'falla-http')->getJson(self::URL)
            ->assertStatus(502)
            ->assertExactJson([
                'error' => 'No se pudo armar el resumen: el servicio de estados no está disponible.',
                'servicio' => 'estados',
                'correlation_id' => 'falla-http',
            ])
            ->assertHeader('X-Correlation-ID', 'falla-http');

        $this->assertStringContainsString('respondió HTTP 500', $this->registro());
    }

    public function test_si_estados_no_responde_devuelve_502_y_lo_registra(): void
    {
        $this->simularServicios(estados: Http::failedConnection('cURL error 7: Failed to connect to 127.0.0.1 port 8002'));

        $this->withHeader('X-Correlation-ID', 'estados-caido')->getJson(self::URL)
            ->assertStatus(502)
            ->assertJsonPath('servicio', 'estados');

        $registro = $this->registro();
        $this->assertStringContainsString('Falla de orquestación', $registro);
        $this->assertStringContainsString('sin conexión', $registro);
        $this->assertStringContainsString('"correlation_id":"estados-caido"', $registro);
    }

    public function test_un_servicio_lento_se_corta_por_timeout(): void
    {
        $this->simularServicios(estados: Http::failedConnection('cURL error 28: Operation timed out after 3001 milliseconds'));

        $this->getJson(self::URL)->assertStatus(502)->assertJsonPath('servicio', 'estados');

        $this->assertStringContainsString('sin respuesta en 3 s (timeout)', $this->registro());
    }

    public function test_un_json_con_otro_formato_se_rechaza(): void
    {
        $this->simularServicios(estados: Http::response(['request' => 101, 'status' => 'pending']));

        $this->getJson(self::URL)->assertStatus(502)->assertJsonPath('servicio', 'estados');

        $this->assertStringContainsString('contenido inválido', $this->registro());
    }

    public function test_si_falla_usuarios_ya_no_se_consulta_estados(): void
    {
        $this->simularServicios(usuarios: Http::failedConnection('cURL error 7: Failed to connect'));

        $this->getJson(self::URL)->assertStatus(502)->assertJsonPath('servicio', 'usuarios');

        Http::assertSentCount(1);
    }

    public function test_solo_acepta_identificadores_numericos(): void
    {
        $this->getJson('/api/resumen-solicitud/abc')->assertNotFound();
    }
}
