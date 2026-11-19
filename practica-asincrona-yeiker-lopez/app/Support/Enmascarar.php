<?php

namespace App\Support;

/**
 * Oculta datos personales antes de guardarlos en la tabla de estados o en el
 * registro: queda lo suficiente para reconocer el dato, no para reutilizarlo.
 */
final class Enmascarar
{
    /** "luis@tienda.test" → "lu***@tienda.test" */
    public static function correo(string $correo): string
    {
        [$usuario, $dominio] = array_pad(explode('@', $correo, 2), 2, '');

        return mb_substr($usuario, 0, 2).'***'.($dominio !== '' ? "@{$dominio}" : '');
    }
}
