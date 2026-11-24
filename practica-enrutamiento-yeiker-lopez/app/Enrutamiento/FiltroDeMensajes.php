<?php

namespace App\Enrutamiento;

use App\Models\EventoRecibido;

/**
 * MESSAGE FILTER.
 *
 * Decide si un evento válido debe seguir hacia el enrutador. Los que no
 * cumplen una condición de aceptación NO llegan a ningún consumidor; la
 * decisión queda registrada como rechazo de negocio (no como error).
 */
final class FiltroDeMensajes
{
    public const REGLAS = [
        [
            'regla' => 'F1',
            'condicion' => 'datos.modo = "prueba"',
            'accion' => 'Rechazar',
            'justificacion' => 'La pasarela envía eventos de su entorno de pruebas; procesarlos crearía comprobantes, envíos o devoluciones de stock que no existen.',
        ],
        [
            'regla' => 'F2',
            'condicion' => 'el mismo "id" ya fue enrutado',
            'accion' => 'Rechazar',
            'justificacion' => 'Un evento repetido (reintento del emisor) no debe facturarse ni devolver stock dos veces.',
        ],
    ];

    public function evaluar(Evento $evento): ResultadoFiltro
    {
        // F1: condición sobre el CONTENIDO del mensaje.
        if ($evento->enModoPrueba()) {
            return ResultadoFiltro::rechazado(
                'F1',
                "El evento {$evento->id} viene de la pasarela en modo prueba: no debe generar comprobantes, envíos ni devoluciones reales.",
            );
        }

        // F2: condición sobre el ESTADO (lo que ya pasó por el canal).
        if ($previo = EventoRecibido::yaEnrutado($evento->id)) {
            return ResultadoFiltro::rechazado(
                'F2',
                "El evento {$evento->id} ya fue recibido y enrutado a «{$previo->destino}»; se descarta para no procesarlo dos veces.",
            );
        }

        return ResultadoFiltro::aceptado();
    }
}
