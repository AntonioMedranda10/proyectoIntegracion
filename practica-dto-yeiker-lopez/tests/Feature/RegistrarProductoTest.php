<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

/**
 * Contrato "Registrar un producto": pruebas P1, P2 y P3 de la guía y casos extra.
 * El seeder deja 3 categorías y 2 productos antes de cada prueba.
 */
class RegistrarProductoTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const URL = '/api/v1/products';

    private const PRODUCTOS_DEL_SEEDER = 2;

    private function productoValido(array $cambios = []): array
    {
        return array_merge([
            'name' => 'Parlante portátil',
            'description' => 'Resistente al agua, 12 h de batería',
            'price' => 35.5,
            'stock' => 20,
            'categoryId' => 1,
        ], $cambios);
    }

    public function test_p1_json_valido_registra_el_producto_y_responde_201(): void
    {
        $this->postJson(self::URL, $this->productoValido())
            ->assertCreated()
            ->assertHeader('Location')
            // Estructura exacta del contrato: ni campos de más ni de menos, y con sus tipos.
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('message', 'Producto registrado correctamente.')
                ->has('data', fn (AssertableJson $data) => $data
                    ->whereType('id', 'integer')
                    ->where('name', 'Parlante portátil')
                    ->where('description', 'Resistente al agua, 12 h de batería')
                    ->where('price', 35.5)
                    ->where('stock', 20)
                    ->where('available', true)
                    ->where('categoryId', 1)
                    ->where('category', ['id' => 1, 'name' => 'Electrónica'])
                    ->where('imageUrl', null)
                    ->whereType('createdAt', 'string')
                )
            );

        $this->assertDatabaseHas('productos', [
            'nombre' => 'Parlante portátil',
            'stock' => 20,
            'categoria_id' => 1,
        ]);
    }

    public function test_p2_falta_un_campo_obligatorio_responde_422_con_el_detalle(): void
    {
        $datos = $this->productoValido();
        unset($datos['name']);

        $this->postJson(self::URL, $datos)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'El nombre (name) es obligatorio.'])
            ->assertJsonMissingValidationErrors(['price', 'stock', 'categoryId']);

        $this->assertDatabaseCount('productos', self::PRODUCTOS_DEL_SEEDER);
    }

    public function test_p3_tipos_de_dato_incorrectos_responden_422_sin_guardar_nada(): void
    {
        $this->postJson(self::URL, $this->productoValido([
            'price' => '35.50',   // texto en lugar de número
            'stock' => 'veinte',  // texto en lugar de entero
            'categoryId' => '1',  // texto en lugar de entero
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['price', 'stock', 'categoryId']);

        $this->assertDatabaseCount('productos', self::PRODUCTOS_DEL_SEEDER);
        $this->assertDatabaseMissing('productos', ['nombre' => 'Parlante portátil']);
    }

    public function test_la_consulta_por_id_devuelve_200_con_el_mismo_contrato(): void
    {
        $creado = $this->postJson(self::URL, $this->productoValido())->json('data');

        $this->getJson(self::URL.'/'.$creado['id'])
            ->assertOk()
            ->assertExactJson(['data' => $creado]);
    }

    public function test_campos_opcionales_vacios_se_guardan_como_null(): void
    {
        $this->postJson(self::URL, $this->productoValido(['description' => '', 'categoryId' => null, 'stock' => 0]))
            ->assertCreated()
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.category', null)
            ->assertJsonPath('data.available', false);
    }

    public function test_un_nombre_repetido_responde_422(): void
    {
        $this->postJson(self::URL, $this->productoValido(['name' => 'Audífonos Bluetooth']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'Ya existe un producto con ese nombre.']);
    }

    public function test_un_cliente_sin_header_accept_igual_recibe_json_422(): void
    {
        // Sin "Accept: application/json", Laravel por defecto redirige (302).
        $respuesta = $this->call('POST', self::URL, server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['stock' => 5]));

        $respuesta->assertUnprocessable()->assertHeader('Content-Type', 'application/json');
        $this->assertArrayHasKey('errors', $respuesta->json());
    }

    public function test_un_producto_inexistente_responde_404_en_json(): void
    {
        $this->getJson(self::URL.'/999')
            ->assertNotFound()
            ->assertExactJson(['message' => 'El recurso solicitado no existe.']);
    }
}
