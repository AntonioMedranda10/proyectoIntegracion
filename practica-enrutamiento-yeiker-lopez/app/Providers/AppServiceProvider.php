<?php

namespace App\Providers;

use App\Enrutamiento\PublicadorEventos;
use App\Enrutamiento\RabbitMq\PublicadorRabbitMq;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Transporte real: RabbitMQ. Las pruebas automáticas lo reemplazan por uno en memoria.
        $this->app->bind(PublicadorEventos::class, PublicadorRabbitMq::class);
    }

    public function boot(): void
    {
        //
    }
}
