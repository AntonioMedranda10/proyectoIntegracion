<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mismos nombres que el contrato de mensajes del proyecto (subject, content).
 * Del autor solo se exponen id y nombre: nunca correo, contraseña ni tokens.
 *
 * @mixin \App\Models\Mensaje
 */
class MensajeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'content' => $this->content,
            'author' => ['id' => $this->autor->id, 'name' => $this->autor->name],
            'createdAt' => $this->created_at->toIso8601ZuluString(),
        ];
    }

    public function jsonOptions(): int
    {
        return JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    }
}
