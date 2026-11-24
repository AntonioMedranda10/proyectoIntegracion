<?php

namespace Tests\Dobles;

use App\Enrutamiento\Destino;
use App\Enrutamiento\Evento;
use App\Enrutamiento\Excepciones\BrokerNoDisponible;
use App\Enrutamiento\PublicadorEventos;

/** Transporte de prueba: guarda [destino, evento] en memoria o simula el broker caído. */
final class PublicadorEnMemoria implements PublicadorEventos
{
    /** @var list<array{0: Destino, 1: Evento}> */
    public array $publicados = [];

    public bool $brokerCaido = false;

    public function publicar(Evento $evento, Destino $destino): void
    {
        if ($this->brokerCaido) {
            throw new BrokerNoDisponible('No se pudo conectar con RabbitMQ en 127.0.0.1:5672 (simulado en la prueba).');
        }
        $this->publicados[] = [$destino, $evento];
    }

    /** Destinos a los que llegó cada evento, por id. */
    public function destinos(): array
    {
        return array_map(fn (array $p) => [$p[1]->id, $p[0]->value], $this->publicados);
    }
}
