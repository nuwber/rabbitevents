<?php

namespace Nuwber\Events;

use Illuminate\Contracts\Container\Container;
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
        $this->registerBroadcastEvents();
        $this->registerContext();
        $this->registerTopic();

        $this->app->singleton(Publisher::class);
    }

    protected function registerBroadcastEvents()
    {
        $this->app->singleton('broadcast.events', function ($app) {
            return new Dispatcher($app);
        });
    }

    protected function registerTopic()
    {
        $this->app->singleton(AmqpTopic::class, function ($app) {
            /** @var AmqpContext $context */
            $context = $app->make(AmqpContext::class);

            $topic = $context->createTopic($this->getExchangeName($app));
            $topic->setType(AmqpTopic::TYPE_TOPIC);
            $topic->addFlag(AmqpTopic::FLAG_DURABLE);

            $context->declareTopic($topic);

            return $topic;
        });
    }

    protected function registerContext()
    {
        $this->app->singleton(AmqpContext::class, function ($app) {
            $defaultConnection = $app['config']['queue.default'];

            return (new ContextFactory())->make($app['config']["queue.connections.$defaultConnection"]);
        });
    }

    private function getExchangeName(Container $app): string
    {
        $config = $app['config']['queue'];
        $connection = Arr::get($config, 'default');

        return Arr::get($config, "connections.$connection.exchange", self::DEFAULT_EXCHANGE_NAME);
    }
}
