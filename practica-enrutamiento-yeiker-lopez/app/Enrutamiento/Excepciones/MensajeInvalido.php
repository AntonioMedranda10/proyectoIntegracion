<?php

namespace App\Enrutamiento\Excepciones;

use RuntimeException;

/** El mensaje que llegó de la cola no cumple el contrato: va a la cola de fallidos. */
class MensajeInvalido extends RuntimeException {}
