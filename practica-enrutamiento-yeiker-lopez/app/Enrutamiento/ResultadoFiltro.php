<?php

namespace App\Enrutamiento;

final readonly class ResultadoFiltro
{
    private function __construct(
        public bool $aceptado,
        public ?string $regla = null,
        public ?string $motivo = null,
    ) {}

    public static function aceptado(): self
    {
        return new self(true);
    }

    public static function rechazado(string $regla, string $motivo): self
    {
        return new self(false, $regla, $motivo);
    }
}
