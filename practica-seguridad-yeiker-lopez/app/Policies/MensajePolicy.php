<?php

namespace App\Policies;

use App\Models\Mensaje;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * AUTORIZACIÓN de los mensajes. Se evalúa después de la autenticación:
 * el usuario ya está identificado; aquí se decide si PUEDE hacer la acción.
 * Si la respuesta es deny, Laravel responde 403 con este mensaje.
 */
class MensajePolicy
{
    /** GET /messages/{id}: el autor del mensaje o un administrador. */
    public function view(User $usuario, Mensaje $mensaje): Response
    {
        return $usuario->esAdministrador() || $mensaje->user_id === $usuario->id
            ? Response::allow()
            : Response::deny('No puedes ver mensajes de otros clientes.');
    }

    /** DELETE /messages/{id}: solo un administrador, aunque el mensaje sea propio. */
    public function delete(User $usuario, Mensaje $mensaje): Response
    {
        return $usuario->esAdministrador()
            ? Response::allow()
            : Response::deny('Solo un administrador puede eliminar mensajes.');
    }
}
