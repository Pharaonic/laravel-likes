<?php

namespace Pharaonic\Laravel\Likes\Tests\Feature;

use Illuminate\Support\ServiceProvider;
use Pharaonic\Laravel\Likes\LikesServiceProvider;
use Pharaonic\Laravel\Likes\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_the_provider_is_registered(): void
    {
        $this->assertNotEmpty($this->app->getProviders(LikesServiceProvider::class));
    }

    public function test_the_configuration_defaults_are_merged(): void
    {
        $this->assertSame('likes', $this->app['config']->get('pharaonic.likes.table'));
        $this->assertTrue($this->app['config']->get('pharaonic.likes.cleanup_on_delete'));
    }

    public function test_the_configuration_is_published_to_config_pharaonic(): void
    {
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
    }

    public function test_the_migrations_are_published_to_database_migrations(): void
    {
        $source = realpath(__DIR__.'/../../database/migrations');

        foreach (['pharaonic', 'laravel-likes', 'pharaonic-migrations', 'likes-migrations'] as $tag) {
            $published = [];

            foreach (ServiceProvider::pathsToPublish(LikesServiceProvider::class, $tag) as $from => $to) {
                $published[realpath($from) ?: $from] = $to;
            }

            $this->assertArrayHasKey($source, $published, $tag);
            $this->assertSame($this->app->databasePath('migrations'), $published[$source]);
        }
    }
}
