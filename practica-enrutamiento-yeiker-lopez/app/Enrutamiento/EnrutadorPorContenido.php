<?php

namespace App\Enrutamiento;

/**
 * CONTENT-BASED ROUTER.
 *
 * Inspecciona el campo "tipo" del evento y elige UN destino según reglas
 * explícitas. Un tipo no reconocido no se descarta: va a no_enrutables para
 * revisión manual.
 *
 * REGLAS es la única fuente de verdad: la usa decidir() y la expone GET /api/v1/reglas.
 */
final class EnrutadorPorContenido
{
    public const REGLAS = [
        [
            'regla' => 'R1',
            'tipo' => 'pago_aprobado',
            'destino' => Destino::Facturacion,
            'justificacion' => 'Un pago aprobado origina el comprobante; solo Facturación lo emite.',
        ],
        [
            'regla' => 'R2',
            'tipo' => 'pedido_despachado',
            'destino' => Destino::Logistica,
            'justificacion' => 'Un despacho necesita guía y transportista; solo Logística los registra.',
        ],
        [
            'regla' => 'R3',
            'tipo' => 'pedido_cancelado',
            'destino' => Destino::Inventario,
            'justificacion' => 'Una cancelación devuelve las unidades al stock; no se factura ni se envía.',
        ],
    ];

    public const REGLA_NO_RECONOCIDO = [
        'regla' => 'R4',
        'tipo' => '(cualquier otro)',
        'destino' => Destino::NoEnrutables,
        'justificacion' => 'Un tipo desconocido no se descarta en silencio: queda en revisión para que una persona decida.',
    ];

    public function decidir(Evento $evento): DecisionEnrutamiento
    {
        foreach (self::REGLAS as $regla) {
            if ($regla['tipo'] === $evento->tipo) {
                return new DecisionEnrutamiento($regla['destino'], $regla['regla'], $regla['justificacion']);
            }
        }

        $regla = self::REGLA_NO_RECONOCIDO;

        return new DecisionEnrutamiento($regla['destino'], $regla['regla'], $regla['justificacion']);
    }

    /** Tipos con destino propio (la validación exige sus campos indispensables). */
    public static function tiposConocidos(): array
    {
        return array_column(self::REGLAS, 'tipo');
    }
}
