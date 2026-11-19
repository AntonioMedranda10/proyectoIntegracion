<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // En producción, detrás de NGINX con TLS, todas las URL que genere Laravel usan https://.
        if ($this->app->environment('production')) {
            URL::forceHttps();
        }
    }
}
