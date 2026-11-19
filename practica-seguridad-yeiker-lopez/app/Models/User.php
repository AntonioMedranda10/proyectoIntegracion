<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROL_CLIENTE = 'customer';

    public const ROL_ADMIN = 'admin';

    /**
     * "role" NO es asignable en masa: nadie puede volverse administrador
     * enviando {"role":"admin"} en una petición.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * Nunca se devuelven en las respuestas JSON.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function esAdministrador(): bool
    {
        return $this->role === self::ROL_ADMIN;
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(Mensaje::class);
    }
}
