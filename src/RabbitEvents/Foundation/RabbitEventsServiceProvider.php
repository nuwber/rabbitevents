<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation;

use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use RabbitEvents\Foundation\Connection\ConnectionFactory;
use RabbitEvents\Foundation\Connection\ConnectionManager;
use RabbitEvents\Foundation\Console\InstallCommand;
use RabbitEvents\Foundation\Serialization\JsonSerializer;

class RabbitEventsServiceProvider extends ServiceProvider
{
    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->app->singleton(Serialization\SerializerRegistry::class, function ($app) {
            $registry = new Serialization\SerializerRegistry();
            $registry->register(
                $app->make($app['config']['rabbitevents.default_serializer'] ?? JsonSerializer::class), 
                true
            );

            return $registry;
        });

        $this->app->singleton(ConnectionFactory::class, fn($app) => new ConnectionFactory($app));

        $this->app->singleton(
            ConnectionManager::class,
            fn($app) => new ConnectionManager($app, $app[ConnectionFactory::class])
        );

        $this->app->singleton(Contracts\Connection::class, static fn($app) => $app[ConnectionManager::class]->connection());

        $this->app->singleton(
            Context::class,
            static fn($app) => new Context(
                $app[Contracts\Connection::class],
                $app[Serialization\SerializerRegistry::class]
            )
        );
    }

    public function register(): void
    {
        $this->registerCommands();
        $this->registerPublishing();
    }

    /**
     * @return array
     * @throws \RuntimeException
     */
    protected function resolveConfig(): array
    {
        $config = $this->app['config']['rabbitevents'];

        $defaultConnection = Arr::get($config, 'default');

        return Arr::get($config, "connections.$defaultConnection", []);
    }

    /**
     * Register RabbitEvent's publishing.
     *
     * @return void
     */
    protected function registerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/config/rabbitevents.php' => $this->app->configPath('rabbitevents.php'),
            ], 'rabbitevents-config');
        }
    }

    protected function registerCommands()
    {
        $this->commands([
            InstallCommand::class,
        ]);
    }
}
