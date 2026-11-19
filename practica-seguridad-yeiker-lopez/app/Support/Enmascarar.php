<?php

namespace App\Support;

/**
 * Oculta secretos y datos personales antes de escribirlos en un registro:
 * queda lo suficiente para rastrear un evento, pero no para reutilizar el dato.
 */
final class Enmascarar
{
    /** "7|tienda_AbC…" → "7|tien…wXyZ" */
    public static function token(?string $token): ?string
    {
        if ($token === null || $token === '') {
            return null;
        }

        return strlen($token) <= 12 ? '***' : substr($token, 0, 6).'…'.substr($token, -4);
    }

    /** "luis@tienda.test" → "lu***@tienda.test" */
    public static function correo(string $correo): string
    {
        [$usuario, $dominio] = array_pad(explode('@', $correo, 2), 2, '');

        return mb_substr($usuario, 0, 2).'***'.($dominio !== '' ? "@{$dominio}" : '');
    }
}
