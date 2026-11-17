<?php

namespace Tests\Feature;

use App\Services\NormalizadorProductos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * El traductor se prueba directamente, sin HTTP ni controladores: por estar en un
 * servicio propio, cualquier parte del sistema puede reutilizarlo.
 */
class NormalizadorProductosTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private NormalizadorProductos $normalizador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizador = app(NormalizadorProductos::class);
    }

    public function test_cada_origen_tiene_su_metodo_y_ambos_devuelven_el_mismo_contrato(): void
    {
        $desdeA = $this->normalizador->desdeInventario([
            'nombre_producto' => 'Lámpara de pie',
            'precio' => '42,9',
            'existencias' => '7',
            'categoria' => 'Hogar',
        ]);
        $desdeB = $this->normalizador->desdeProveedor([
            'product' => [
                'title' => 'Lámpara de pie',
                'pricing' => ['amount_cents' => 4290, 'currency' => 'USD'],
                'inventory' => ['available' => 7],
                'category_code' => 'HOME',
            ],
        ]);

        $this->assertSame(
            ['name' => 'Lámpara de pie', 'description' => null, 'price' => 42.9, 'stock' => 7, 'categoryId' => 2],
            $desdeA,
        );
        $this->assertSame($desdeA, $desdeB);
    }

    public function test_limpia_espacios_mayusculas_y_textos_vacios(): void
    {
        $contrato = $this->normalizador->desdeInventario([
            'nombre_producto' => '  Parlante portátil  ',
            'descripcion' => '   ',
            'precio' => ' 35,50 ',
            'existencias' => '20',
            'categoria' => 'ELECTRÓNICA',
        ]);

        $this->assertSame('Parlante portátil', $contrato['name']);
        $this->assertNull($contrato['description']);
        $this->assertSame(1, $contrato['categoryId']);
    }

    public function test_reune_todos_los_errores_del_origen_en_una_sola_respuesta(): void
    {
        try {
            $this->normalizador->desdeProveedor([
                'product' => [
                    'pricing' => ['amount_cents' => '3550', 'currency' => 'USD'],
                    'inventory' => ['available' => -1],
                ],
            ]);
            $this->fail('Se esperaba un error de validación.');
        } catch (ValidationException $e) {
            $this->assertSame(
                ['product.title', 'product.pricing.amount_cents', 'product.inventory.available'],
                array_keys($e->errors()),
            );
        }
    }
}
