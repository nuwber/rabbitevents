<?php

namespace RabbitEvents\Foundation;

use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use RabbitEvents\Foundation\Commands\InstallCommand;

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

        $this->app->singleton(
            Context::class,
            static fn($app) => new Context(new Connection($config))
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
                __DIR__ . '/config/rabbitevents.php' => config_path('rabbitevents.php'),
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
