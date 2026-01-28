<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation;

use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Interop\Queue\Topic;
use RabbitEvents\Foundation\Commands\InstallCommand;
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
        $config = $this->resolveConfig();

        $this->app->singleton(Serialization\SerializerRegistry::class, function ($app) use ($config) {
            $registry = new Serialization\SerializerRegistry();
            
            $registry->register(new Serialization\JsonSerializer());
            $registry->register(new Serialization\ProtobufSerializer());

            return $registry;
        });

        // Publisher needs a default serializer
        $this->app->singleton(Contracts\Serializer::class, function ($app) use ($config) {
            $class = Arr::get($config, 'default_serializer', JsonSerializer::class);

            return new $class;
        });

        $this->app->singleton(Contracts\Connection::class, function ($app) use ($config) {
             return new Amqp\Connection($config);
        });
        
        $this->app->singleton(
            Context::class,
            static fn($app) => new Context(
                $app[Contracts\Connection::class], 
                $app[Serialization\SerializerRegistry::class]
            )
        );

        $this->app->singleton(Topic::class);
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
            InstallCommand::class
        ]);
    }
}
