<?php

namespace Nuwber\Events;

use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpTopic;
use Nuwber\Events\Console\EventsListCommand;
use Nuwber\Events\Console\ListenCommand;
use Nuwber\Events\Console\ObserverMakeCommand;
use Nuwber\Events\Event\Publisher;
use Nuwber\Events\Queue\ContextFactory;

class BroadcastEventServiceProvider extends ServiceProvider
{
    protected $listen = [];

    const DEFAULT_EXCHANGE_NAME = 'events';

    /**
     * Register any events for your application.
     *
     * @return void
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function boot()
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            ListenCommand::class,
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
        if (!$config = $this->resolveConfig()) {
            return;
        }

        $this->registerBroadcastEvents();
        $this->registerContext($config);
        $this->registerTopic($config);

        $this->app->singleton(Publisher::class);
    }

    protected function resolveConfig()
    {
        $defaultConnection = $this->app['config']['queue.default'];

        if ($this->app['config']["queue.connections.$defaultConnection.driver"] == 'rabbitmq') {
            return $this->app['config']["queue.connections.$defaultConnection"];
        }

        foreach ($this->app['config']["queue.connections"] as $connection => $config) {
            if (Arr::get($config, 'driver') == 'rabbitmq') {
                return $config;
            }
        }

        return [];
    }

    protected function registerBroadcastEvents()
    {
        $this->app->singleton('broadcast.events', function ($app) {
            return new Dispatcher($app);
        });
    }

    protected function registerTopic(array $config)
    {
        $this->app->singleton(AmqpTopic::class, function ($app) use ($config) {
            /** @var AmqpContext $context */
            $context = $app->make(AmqpContext::class);

            $topic = $context->createTopic(Arr::get($config, 'exchange', self::DEFAULT_EXCHANGE_NAME));
            $topic->setType(AmqpTopic::TYPE_TOPIC);
            $topic->addFlag(AmqpTopic::FLAG_DURABLE);

            $context->declareTopic($topic);

            return $topic;
        });
    }

    protected function registerContext(array $config)
    {
        $this->app->singleton(AmqpContext::class, function ($app) use ($config) {
            return (new ContextFactory())->make($config);
        });
    }
}
