<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Response;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Establecer charset UTF-8 por defecto para respuestas HTML
        Response::macro('charset', function ($charset = 'utf-8') {
            $this->headers->set('Content-Type', 'text/html; charset=' . $charset);
            return $this;
        });
    }
}
