<?php

namespace Nuwber\Events;

use Illuminate\Container\Container;
use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\AmqpMessage;
use Interop\Queue\PsrContext;

class JobsFactory
{
    /**
     * @var AmqpConsumer
     */
    private $consumer;
    /**
     * @var Container
     */
    private $app;
    /**
     * @var string
     */
    private $connectionName;
    /** @var PsrContext */
    private $context;

    public function __construct(
        Container $container,
        AmqpConsumer $consumer,
        string $connectionName
    ) {
        $this->app = $container;
        $this->consumer = $consumer;
        $this->context = $this->app->make(PsrContext::class);
        $this->connectionName = $connectionName;
    }

    public function make(AmqpMessage $message)
    {
        foreach ($this->getListeners($message) as $listener => $listeners) {
            foreach ($listeners as $callback) {
                yield new Job(
                    $this->app,
                    $this->context,
                    $this->consumer,
                    $message,
                    $this->connectionName,
                    $callback,
                    $listener
                );
            }
        }
    }

    /**
     * @return array
     */
    protected function getListeners(AmqpMessage $message)
    {
        return $this->app->make('broadcast.events')
            ->getListeners($message->getRoutingKey());
    }
}
