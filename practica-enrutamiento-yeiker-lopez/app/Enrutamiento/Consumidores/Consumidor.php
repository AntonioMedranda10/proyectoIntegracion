<?php

namespace App\Enrutamiento\Consumidores;

use App\Enrutamiento\Destino;
use App\Enrutamiento\Evento;
use App\Enrutamiento\Excepciones\EventoNoProcesable;

/** Un proceso del proyecto que recibe SOLO los eventos de su destino. */
interface Consumidor
{
    public function destino(): Destino;

    /** Tipos de evento que este consumidor sabe procesar; cualquier otro se rechaza. */
    public function tipos(): array;

    /**
     * Hace el trabajo del proceso y devuelve un resumen de lo que hizo.
     *
     * @throws EventoNoProcesable error permanente
     */
    public function procesar(Evento $evento): string;
}
