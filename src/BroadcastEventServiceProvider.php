<?php

namespace Butik\Events;

use Illuminate\Contracts\Queue\Factory as QueueFactoryContract;
use Illuminate\Support\ServiceProvider;
use Interop\Amqp\AmqpTopic;
use Interop\Queue\PsrContext;
use Interop\Queue\PsrTopic;

/**
 * @author Sergey Kvartnikov <s.kvartnikov@butik.ru>
 *
 * Created at 15.11.2018
 */
class BroadcastEventServiceProvider extends ServiceProvider
{
    /**
     * @var string
     */
    private $exchangeName = 'events';

    /**
     * @return void
     */
    public function register(): void
    {
        $this->registerBroadcastEvents();
        $this->registerQueueContext();
        $this->registerPsrTopic();
        $this->registerMessageFactory();
        $this->registerEventProducer();
    }

    /**
     * @return void
     */
    protected function registerBroadcastEvents(): void
    {
        $this->app->singleton('events', function ($app) {
            return (new Dispatcher($app))
                ->setQueueResolver(function () use ($app) {
                    return $app->make(QueueFactoryContract::class);
                });
        });
    }

    /**
     * @return void
     */
    protected function registerQueueContext(): void
    {
        $this->app->singleton(PsrContext::class, function ($app) {
            return $app['queue']->connection($app['config']['queue']['broadcast_events'] ?? '')->getPsrContext();
        });
    }

    /**
     * @return void
     */
    protected function registerPsrTopic(): void
    {
        $this->app->singleton(PsrTopic::class, function ($app) {
            $context = $app->make(PsrContext::class);

            $topic = $context->createTopic($this->exchangeName);
            $topic->setType(AmqpTopic::TYPE_TOPIC);
            $topic->addFlag(AmqpTopic::FLAG_DURABLE);

            $context->declareTopic($topic);

            return $topic;
        });
    }

    protected function registerMessageFactory(): void
    {
        $this->app->singleton(MessageFactory::class);
    }

    protected function registerEventProducer(): void
    {
        $this->app->singleton(BroadcastFactory::class);
    }
}
