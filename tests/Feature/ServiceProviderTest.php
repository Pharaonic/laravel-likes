<?php

use Illuminate\Support\ServiceProvider;
use Pharaonic\Laravel\Likes\LikesServiceProvider;

test('the provider is registered', function () {
    $this->assertNotEmpty($this->app->getProviders(LikesServiceProvider::class));
});

test('the configuration defaults are merged', function () {
    $this->assertSame('likes', $this->app['config']->get('pharaonic.likes.table'));
    $this->assertTrue($this->app['config']->get('pharaonic.likes.cleanup_on_delete'));
});

test('the configuration is published to config pharaonic', function () {
    $source = realpath(__DIR__.'/../../config/likes.php');

    foreach (['pharaonic', 'laravel-likes', 'pharaonic-config', 'likes-config'] as $tag) {
        $paths = ServiceProvider::pathsToPublish(LikesServiceProvider::class, $tag);
        $published = [];

        foreach ($paths as $from => $to) {
            $published[realpath($from) ?: $from] = $to;
        }

        $this->assertArrayHasKey($source, $published, $tag);
        $this->assertSame($this->app->configPath('pharaonic/likes.php'), $published[$source]);
    }
});

test('the migrations are published to database migrations', function () {
    $source = realpath(__DIR__.'/../../database/migrations');

    foreach (['pharaonic', 'laravel-likes', 'pharaonic-migrations', 'likes-migrations'] as $tag) {
        $published = [];

        foreach (ServiceProvider::pathsToPublish(LikesServiceProvider::class, $tag) as $from => $to) {
            $published[realpath($from) ?: $from] = $to;
        }

        $this->assertArrayHasKey($source, $published, $tag);
        $this->assertSame($this->app->databasePath('migrations'), $published[$source]);
    }
});
