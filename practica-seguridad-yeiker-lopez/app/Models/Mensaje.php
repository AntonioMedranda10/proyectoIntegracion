<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Mensaje de un cliente hacia la tienda (contacto o soporte). */
class Mensaje extends Model
{
    protected $table = 'mensajes';

    // user_id NO es asignable: el autor siempre es el dueño del token, nunca un dato del cuerpo.
    protected $fillable = ['subject', 'content'];

    protected function casts(): array
    {
        return ['user_id' => 'integer'];
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
