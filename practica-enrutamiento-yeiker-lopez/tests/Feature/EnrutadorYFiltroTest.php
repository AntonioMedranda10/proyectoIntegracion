<?php

namespace Tests\Feature;

use App\Enrutamiento\Destino;
use App\Enrutamiento\EnrutadorPorContenido;
use App\Enrutamiento\Evento;
use App\Enrutamiento\FiltroDeMensajes;
use App\Models\EventoRecibido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Reglas del Content-Based Router y del Message Filter, sin HTTP ni broker. */
class EnrutadorYFiltroTest extends TestCase
{
    use RefreshDatabase;

    private function evento(string $tipo, array $datos = [], int $id = 5001): Evento
    {
        return new Evento($id, $tipo, 'normal', 'prueba-1', '2026-11-24T10:00:00.000Z', $datos);
    }

    public static function reglasDelEnrutador(): array
    {
        return [
            'R1 pago aprobado → facturación' => ['pago_aprobado', Destino::Facturacion, 'R1'],
            'R2 pedido despachado → logística' => ['pedido_despachado', Destino::Logistica, 'R2'],
            'R3 pedido cancelado → inventario' => ['pedido_cancelado', Destino::Inventario, 'R3'],
            'R4 tipo desconocido → no_enrutables' => ['devolucion_solicitada', Destino::NoEnrutables, 'R4'],
        ];
    }

    #[DataProvider('reglasDelEnrutador')]
    public function test_el_enrutador_elige_un_unico_destino_por_tipo(string $tipo, Destino $esperado, string $regla): void
    {
        $decision = (new EnrutadorPorContenido)->decidir($this->evento($tipo));

        $this->assertSame($esperado, $decision->destino);
        $this->assertSame($regla, $decision->regla);
    }

    public function test_el_filtro_acepta_un_evento_real_y_nuevo(): void
    {
        $this->assertTrue((new FiltroDeMensajes)->evaluar($this->evento('pago_aprobado', ['modo' => 'real']))->aceptado);
    }

    public function test_f1_rechaza_eventos_en_modo_prueba(): void
    {
        $resultado = (new FiltroDeMensajes)->evaluar($this->evento('pago_aprobado', ['modo' => 'prueba']));

        $this->assertFalse($resultado->aceptado);
        $this->assertSame('F1', $resultado->regla);
    }

    public function test_f2_rechaza_un_id_que_ya_fue_enrutado(): void
    {
        EventoRecibido::create([
            'evento_id' => 5001, 'correlation_id' => 'previo', 'tipo' => 'pago_aprobado', 'prioridad' => 'alta',
            'decision' => EventoRecibido::ENRUTADO, 'regla' => 'R1', 'destino' => 'facturacion', 'recibido_en' => now(),
        ]);

        $resultado = (new FiltroDeMensajes)->evaluar($this->evento('pago_aprobado', [], 5001));

        $this->assertFalse($resultado->aceptado);
        $this->assertSame('F2', $resultado->regla);
        $this->assertStringContainsString('facturacion', $resultado->motivo);
    }

    public function test_un_id_que_antes_fallo_por_transporte_puede_reintentarse(): void
    {
        EventoRecibido::create([
            'evento_id' => 5001, 'correlation_id' => 'previo', 'tipo' => 'pago_aprobado', 'prioridad' => 'alta',
            'decision' => EventoRecibido::ERROR_TRANSPORTE, 'regla' => 'R1', 'recibido_en' => now(),
        ]);

        $this->assertTrue((new FiltroDeMensajes)->evaluar($this->evento('pago_aprobado', [], 5001))->aceptado);
    }
}
