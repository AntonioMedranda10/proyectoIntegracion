<?php

namespace App\Providers;

use App\Mensajeria\PublicadorMensajes;
use App\Mensajeria\RabbitMq\PublicadorRabbitMq;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // El productor usa RabbitMQ. Las pruebas automáticas lo reemplazan por uno en memoria.
        $this->app->bind(PublicadorMensajes::class, PublicadorRabbitMq::class);
    }

    public function boot(): void
    {
        //
    }
}
