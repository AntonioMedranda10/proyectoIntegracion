<?php

namespace App\Enrutamiento;

use App\Models\EventoRecibido;

/** Cómo terminó un evento en el canal de entrada. */
final readonly class ResultadoEntrada
{
    private function __construct(
        public string $decision,
        public Evento $evento,
        public EventoRecibido $registro,
        public ?DecisionEnrutamiento $enrutamiento = null,
        public ?ResultadoFiltro $filtro = null,
        public ?string $errorTransporte = null,
    ) {}

    public static function enrutado(Evento $evento, DecisionEnrutamiento $decision, EventoRecibido $registro): self
    {
        $tipo = $decision->esRevision() ? EventoRecibido::NO_ENRUTABLE : EventoRecibido::ENRUTADO;

        return new self($tipo, $evento, $registro, enrutamiento: $decision);
    }

    public static function filtrado(Evento $evento, ResultadoFiltro $filtro, EventoRecibido $registro): self
    {
        return new self(EventoRecibido::FILTRADO, $evento, $registro, filtro: $filtro);
    }

    public static function errorTransporte(Evento $evento, DecisionEnrutamiento $decision, string $motivo, EventoRecibido $registro): self
    {
        return new self(EventoRecibido::ERROR_TRANSPORTE, $evento, $registro, enrutamiento: $decision, errorTransporte: $motivo);
    }
}
