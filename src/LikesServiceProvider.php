<?php

namespace Pharaonic\Laravel\Likes;

use Illuminate\Support\ServiceProvider;

final class LikesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/likes.php', 'pharaonic.likes');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/likes.php' => $this->app->configPath('pharaonic/likes.php'),
            ], ['pharaonic', 'laravel-likes', 'pharaonic-config', 'likes-config']);

            $this->publishes([
                __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
            ], ['pharaonic', 'laravel-likes', 'pharaonic-migrations', 'likes-migrations']);
        }
    }
}
