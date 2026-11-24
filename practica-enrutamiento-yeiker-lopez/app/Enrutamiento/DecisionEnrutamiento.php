<?php

namespace App\Enrutamiento;

/** Resultado del Content-Based Router: a qué destino va el evento y por qué regla. */
final readonly class DecisionEnrutamiento
{
    public function __construct(
        public Destino $destino,
        public string $regla,
        public string $justificacion,
    ) {}

    public function esRevision(): bool
    {
        return $this->destino === Destino::NoEnrutables;
    }
}
