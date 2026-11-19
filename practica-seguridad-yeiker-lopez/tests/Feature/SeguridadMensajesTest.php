<?php

namespace Tests\Feature;

use App\Models\Mensaje;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Seguridad de /api/v1/messages: P1 (2xx), P2 (401), P3 (403) y protección de secretos.
 * Ana y Luis son clientes; Ana tiene 2 mensajes y Luis 1.
 */
class SeguridadMensajesTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'Clave-De-Prueba-2026';

    private User $admin;

    private User $ana;

    private User $luis;

    private Mensaje $deAna;

    private Mensaje $deLuis;

    private string $registro;

    protected function setUp(): void
    {
        parent::setUp();

        // El registro de seguridad va a un archivo propio de las pruebas.
        $this->registro = storage_path('logs/seguridad-pruebas.log');
        @unlink($this->registro);
        config(['logging.channels.seguridad.path' => $this->registro, 'sanctum.expiration' => 120]);

        $this->admin = User::factory()->create(['email' => 'admin@tienda.test', 'password' => self::CLAVE, 'role' => User::ROL_ADMIN]);
        $this->ana = User::factory()->create(['email' => 'ana@tienda.test', 'password' => self::CLAVE]);
        $this->luis = User::factory()->create(['email' => 'luis@tienda.test', 'password' => self::CLAVE]);

        $this->deAna = $this->ana->mensajes()->create(['subject' => 'Estado de mi pedido', 'content' => '¿Cuándo llega mi pedido #3?']);
        $this->ana->mensajes()->create(['subject' => 'Factura', 'content' => 'Necesito la factura del pedido #3.']);
        $this->deLuis = $this->luis->mensajes()->create(['subject' => 'Garantía', 'content' => '¿Aplica la garantía?']);
    }

    protected function tearDown(): void
    {
        @unlink($this->registro);
        parent::tearDown();
    }

    /** Inicia sesión por la API real y devuelve el token Bearer. */
    private function tokenDe(User $usuario): string
    {
        return $this->postJson('/api/v1/auth/login', ['email' => $usuario->email, 'password' => self::CLAVE])
            ->assertOk()
            ->json('access_token');
    }

    /** Hace una petición con un token. Se olvida el usuario anterior, como en peticiones reales separadas. */
    private function conToken(string $token, string $metodo, string $ruta, array $datos = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->json($metodo, $ruta, $datos);
    }

    // ------------------------------------------------------------ P1: acceso permitido (2xx)

    public function test_p1_el_cliente_lista_solo_sus_mensajes(): void
    {
        $respuesta = $this->conToken($this->tokenDe($this->ana), 'GET', '/api/v1/messages')->assertOk();

        $this->assertCount(2, $respuesta->json('data'));
        $this->assertSame([$this->ana->id], array_values(array_unique(array_column(array_column($respuesta->json('data'), 'author'), 'id'))));
    }

    public function test_p1_el_administrador_lista_todos_los_mensajes(): void
    {
        $this->conToken($this->tokenDe($this->admin), 'GET', '/api/v1/messages')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_p1_el_cliente_ve_su_propio_mensaje(): void
    {
        $this->conToken($this->tokenDe($this->ana), 'GET', "/api/v1/messages/{$this->deAna->id}")
            ->assertOk()
            ->assertJsonPath('data.subject', 'Estado de mi pedido');
    }

    public function test_p1_el_mensaje_nuevo_siempre_queda_a_nombre_del_dueno_del_token(): void
    {
        // Aunque el cuerpo intente asignar el mensaje a Luis, el autor sale del token (Ana).
        $this->conToken($this->tokenDe($this->ana), 'POST', '/api/v1/messages', [
            'subject' => 'Cambio de dirección',
            'content' => 'Quiero cambiar la dirección de entrega.',
            'user_id' => $this->luis->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.author.id', $this->ana->id);

        $this->assertDatabaseHas('mensajes', ['subject' => 'Cambio de dirección', 'user_id' => $this->ana->id]);
    }

    public function test_p1_el_administrador_elimina_un_mensaje(): void
    {
        $this->conToken($this->tokenDe($this->admin), 'DELETE', "/api/v1/messages/{$this->deLuis->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Mensaje eliminado.');

        $this->assertDatabaseMissing('mensajes', ['id' => $this->deLuis->id]);
    }

    // ------------------------------------------------------------ P2: sin autenticación (401)

    public function test_p2_sin_token_responde_401_y_no_modifica_nada(): void
    {
        $this->getJson('/api/v1/messages')->assertUnauthorized()
            ->assertJsonPath('message', 'No autenticado: envía un token válido en la cabecera Authorization: Bearer.');
        $this->postJson('/api/v1/messages', ['subject' => 'x', 'content' => 'y'])->assertUnauthorized();
        $this->deleteJson("/api/v1/messages/{$this->deAna->id}")->assertUnauthorized();

        $this->assertDatabaseCount('mensajes', 3);
    }

    public function test_p2_un_token_inventado_responde_401(): void
    {
        $this->conToken('5|token-inventado-por-un-atacante', 'GET', '/api/v1/messages')->assertUnauthorized();
    }

    public function test_p2_un_token_revocado_con_logout_responde_401(): void
    {
        $token = $this->tokenDe($this->luis);

        $this->conToken($token, 'POST', '/api/v1/auth/logout')->assertOk();
        $this->conToken($token, 'GET', '/api/v1/messages')->assertUnauthorized();
    }

    public function test_p2_un_token_vencido_responde_401(): void
    {
        $token = $this->tokenDe($this->ana);

        $this->travel(121)->minutes();

        $this->conToken($token, 'GET', '/api/v1/messages')->assertUnauthorized();
    }

    // ------------------------------------------------------------ P3: autenticado sin permiso (403)

    public function test_p3_un_cliente_no_puede_ver_mensajes_de_otro_cliente(): void
    {
        $this->conToken($this->tokenDe($this->luis), 'GET', "/api/v1/messages/{$this->deAna->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'No puedes ver mensajes de otros clientes.');
    }

    public function test_p3_un_cliente_no_puede_eliminar_mensajes_ni_siquiera_los_propios(): void
    {
        $this->conToken($this->tokenDe($this->ana), 'DELETE', "/api/v1/messages/{$this->deAna->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Solo un administrador puede eliminar mensajes.');

        $this->assertDatabaseHas('mensajes', ['id' => $this->deAna->id]);
    }

    // ------------------------------------------------------------ inicio de sesión

    public function test_las_credenciales_incorrectas_responden_401_sin_revelar_si_la_cuenta_existe(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'ana@tienda.test', 'password' => 'clave-equivocada'])
            ->assertUnauthorized()->assertJsonPath('message', 'Credenciales inválidas.');
        $this->postJson('/api/v1/auth/login', ['email' => 'nadie@tienda.test', 'password' => 'clave-equivocada'])
            ->assertUnauthorized()->assertJsonPath('message', 'Credenciales inválidas.');
    }

    public function test_el_inicio_de_sesion_se_bloquea_despues_de_5_intentos(): void
    {
        foreach (range(1, 5) as $intento) {
            $this->postJson('/api/v1/auth/login', ['email' => 'ana@tienda.test', 'password' => "intento-{$intento}"])->assertUnauthorized();
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'ana@tienda.test', 'password' => self::CLAVE])->assertStatus(429);
    }

    // ------------------------------------------------------------ P4: secretos protegidos

    public function test_el_registro_de_seguridad_no_guarda_claves_ni_tokens_completos(): void
    {
        $token = $this->tokenDe($this->ana);
        $this->postJson('/api/v1/auth/login', ['email' => 'luis@tienda.test', 'password' => 'Intento-Malicioso-99'])->assertUnauthorized();
        $this->conToken($token.'-alterado', 'GET', '/api/v1/messages')->assertUnauthorized();

        $registro = file_get_contents($this->registro);

        $this->assertStringContainsString('Inicio de sesión', $registro);
        $this->assertStringContainsString('lu***@tienda.test', $registro);
        $this->assertStringNotContainsString(self::CLAVE, $registro);
        $this->assertStringNotContainsString('Intento-Malicioso-99', $registro);
        $this->assertStringNotContainsString($token, $registro);
        $this->assertStringNotContainsString('luis@tienda.test', $registro);
    }

    public function test_las_respuestas_de_error_no_devuelven_el_token(): void
    {
        $token = $this->tokenDe($this->luis);

        $denegado = $this->conToken($token, 'GET', "/api/v1/messages/{$this->deAna->id}")->assertForbidden();
        $invalido = $this->conToken($token.'x', 'GET', '/api/v1/messages')->assertUnauthorized();

        $this->assertStringNotContainsString($token, $denegado->getContent());
        $this->assertStringNotContainsString($token, $invalido->getContent());
    }
}
