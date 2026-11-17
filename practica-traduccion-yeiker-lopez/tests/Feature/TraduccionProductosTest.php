<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Práctica 2.3: dos orígenes heterogéneos → el mismo contrato común (P1, P2, P3 y casos extra).
 * El seeder deja 3 categorías (1 = Electrónica) y 2 productos antes de cada prueba.
 */
class TraduccionProductosTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const PRODUCTOS_DEL_SEEDER = 2;

    /** Lo que deben producir los dos orígenes: el contrato común de la práctica 2.2. */
    private const CONTRATO_ESPERADO = [
        'name' => 'Parlante portátil',
        'description' => 'Resistente al agua, 12 h de batería',
        'price' => 35.5,
        'stock' => 20,
        'categoryId' => 1,
    ];

    /** Origen A: fila de la hoja de inventario (todo texto, coma decimal, categoría por nombre). */
    private function origenA(array $cambios = []): array
    {
        return array_merge([
            'nombre_producto' => 'Parlante portátil',
            'descripcion' => 'Resistente al agua, 12 h de batería',
            'precio' => '35,50',
            'existencias' => '20',
            'categoria' => 'Electrónica',
        ], $cambios);
    }

    /** Origen B: producto del proveedor (inglés, anidado, centavos, código de categoría). */
    private function origenB(array $cambios = []): array
    {
        return array_replace_recursive([
            'product' => [
                'title' => 'Parlante portátil',
                'details' => 'Resistente al agua, 12 h de batería',
                'pricing' => ['amount_cents' => 3550, 'currency' => 'USD'],
                'inventory' => ['available' => 20],
                'category_code' => 'ELEC',
            ],
        ], $cambios);
    }

    public function test_p1_el_origen_a_valido_produce_el_contrato_comun(): void
    {
        $respuesta = $this->postJson('/api/v1/normalize/inventory', $this->origenA());

        $respuesta->assertOk()->assertJsonPath('source', 'inventory');
        // assertSame: mismos valores, mismos tipos (35.5 número, 20 entero) y mismo orden de campos.
        $this->assertSame(self::CONTRATO_ESPERADO, $respuesta->json('data'));
        $this->assertDatabaseCount('productos', self::PRODUCTOS_DEL_SEEDER);
    }

    public function test_p2_el_origen_b_equivalente_produce_el_mismo_contrato_y_tipos(): void
    {
        $desdeA = $this->postJson('/api/v1/normalize/inventory', $this->origenA())->assertOk()->json('data');
        $desdeB = $this->postJson('/api/v1/normalize/supplier', $this->origenB())->assertOk()->json('data');

        $this->assertSame($desdeA, $desdeB);
        $this->assertSame(self::CONTRATO_ESPERADO, $desdeB);
    }

    public function test_p3_un_monto_invalido_responde_422_explicito_sin_guardar(): void
    {
        $this->postJson('/api/v1/import/inventory', $this->origenA(['precio' => '35.50']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['precio' => 'es ambiguo: esta hoja usa coma decimal']);

        $this->postJson('/api/v1/import/inventory', $this->origenA(['precio' => 'treinta']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['precio' => 'no es un monto válido']);

        $this->assertDatabaseCount('productos', self::PRODUCTOS_DEL_SEEDER);
    }

    public function test_p3_un_campo_faltante_responde_422_explicito_sin_guardar(): void
    {
        $sinPrecio = $this->origenB();
        unset($sinPrecio['product']['pricing']);

        $this->postJson('/api/v1/import/supplier', $sinPrecio)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product.pricing.amount_cents', 'product.pricing.currency']);

        $this->assertDatabaseCount('productos', self::PRODUCTOS_DEL_SEEDER);
    }

    public function test_importar_desde_el_origen_a_responde_con_el_contrato_de_la_2_2(): void
    {
        $this->postJson('/api/v1/import/inventory', $this->origenA())
            ->assertCreated()
            ->assertHeader('Location')
            ->assertJsonPath('message', 'Producto importado desde la hoja de inventario.')
            ->assertJsonPath('source', 'inventory')
            ->assertJsonPath('data.name', 'Parlante portátil')
            ->assertJsonPath('data.price', 35.5)
            ->assertJsonPath('data.category', ['id' => 1, 'name' => 'Electrónica']);

        $this->assertDatabaseHas('productos', ['nombre' => 'Parlante portátil', 'stock' => 20, 'categoria_id' => 1]);
    }

    public function test_las_reglas_del_contrato_se_aplican_igual_a_ambos_origenes(): void
    {
        $this->postJson('/api/v1/import/inventory', $this->origenA())->assertCreated();

        // El mismo producto desde el proveedor choca con la regla "nombre único" de la 2.2.
        $this->postJson('/api/v1/import/supplier', $this->origenB())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'Ya existe un producto con ese nombre.']);
    }

    public function test_una_moneda_distinta_de_usd_se_rechaza_sin_convertir(): void
    {
        $this->postJson('/api/v1/normalize/supplier', $this->origenB(['product' => ['pricing' => ['currency' => 'EUR']]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product.pricing.currency' => 'solo precios en USD']);
    }

    public function test_una_categoria_desconocida_se_rechaza_indicando_los_valores_validos(): void
    {
        $this->postJson('/api/v1/normalize/inventory', $this->origenA(['categoria' => 'Juguetes']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['categoria' => 'Valores válidos: Accesorios, Electrónica, Hogar.']);

        $this->postJson('/api/v1/normalize/supplier', $this->origenB(['product' => ['category_code' => 'TOYS']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product.category_code' => 'Valores válidos: ELEC, HOME, ACC.']);
    }

    public function test_una_cantidad_con_decimales_se_rechaza(): void
    {
        $this->postJson('/api/v1/normalize/inventory', $this->origenA(['existencias' => '20.5']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['existencias' => 'no es una cantidad válida']);
    }

    public function test_un_origen_desconocido_responde_404(): void
    {
        $this->postJson('/api/v1/normalize/excel', $this->origenA())->assertNotFound();
    }
}
