<?php

namespace App\Enrutamiento\Consumidores;

use App\Enrutamiento\Evento;

final readonly class ResultadoConsumo
{
    public function __construct(
        public Decision $decision,
        public string $detalle,
        public ?Evento $evento = null,
    ) {}
}
