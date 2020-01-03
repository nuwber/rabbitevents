<?php

namespace Nuwber\Events;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpTopic;
use Nuwber\Events\Console\EventsListCommand;
use Nuwber\Events\Console\InstallCommand;
use Nuwber\Events\Console\ListenCommand;
use Nuwber\Events\Console\ObserverMakeCommand;
use Nuwber\Events\Event\Publisher;
use Nuwber\Events\Queue\ContextFactory;

class RabbitEventsServiceProvider extends ServiceProvider
{
    protected $listen = [];

    const DEFAULT_EXCHANGE_NAME = 'events';

    /**
     * Register any events for your application.
     *
     * @return void
     * @throws BindingResolutionException
     */
    public function boot()
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            ListenCommand::class,
            InstallCommand::class,
            EventsListCommand::class,
            ObserverMakeCommand::class,
        ]);

        $dispatcher = $this->app->make('broadcast.events');

        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                $dispatcher->listen($event, $listener);
            }
        }
    }

    public function register()
    {
        $config = $this->resolveConfig();

        $this->offerPublishing();

        $this->registerRabbitEvents();
        $this->registerContext($config);
        $this->registerTopic($config);

        $this->app->singleton(Publisher::class);
    }

    /**
     * @return array
     * @throws \RuntimeException
     */
    protected function resolveConfig(): array
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/rabbitevents.php',
            'rabbitevents'
        );

        $config = $this->app['config']['rabbitevents'];

        $defaultConnection = Arr::get($config, 'default');

        return Arr::get($config, "connections.$defaultConnection", []);
    }

    /**
     * @return void
     */
    protected function registerRabbitEvents(): void
    {
        $this->app->singleton('broadcast.events', function ($app) {
            return new Dispatcher($app);
        });
    }

    /**
     * @param array $config
     * @return void
     */
    protected function registerTopic(array $config): void
    {
        $this->app->singleton(AmqpTopic::class, function (Container $app) use ($config) {
            /** @var AmqpContext $context */
            $context = $app->make(AmqpContext::class);

            $topic = $context->createTopic(Arr::get($config, 'exchange', self::DEFAULT_EXCHANGE_NAME));
            $topic->setType(AmqpTopic::TYPE_TOPIC);
            $topic->addFlag(AmqpTopic::FLAG_DURABLE);

            $context->declareTopic($topic);

            return $topic;
        });
    }

    /**
     * @param array $config
     * @return void
     */
    protected function registerContext(array $config): void
    {
        $this->app->singleton(AmqpContext::class, function ($app) use ($config) {
            return (new ContextFactory())->make($config);
        });
    }

    /**
     * Setup the resource publishing groups for RabbitEvents.
     *
     * @return void
     */
    protected function offerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $providerName = 'RabbitEventsServiceProvider';

            $this->publishes([
                __DIR__ . "/../stubs/{$providerName}.stub" => $this->app->path("Providers/{$providerName}.php"),
            ], 'rabbitevents-provider');
            $this->publishes([
                __DIR__ . '/../config/rabbitevents.php' => $this->app->configPath('rabbitevents.php'),
            ], 'rabbitevents-config');
        }
    }
}
