<?php

namespace Nuwber\Events;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpMessage;

class JobsFactory
{
    /**
     * @var AmqpConsumer
     */
    private $consumer;
    /**
     * @var Container
     */
    private $container;
    /**
     * @var AmqpContext
     */
    private $context;

    public function __construct(Container $container, AmqpContext $context, AmqpConsumer $consumer)
    {
        $this->container = $container;
        $this->consumer = $consumer;
        $this->context = $context;
    }

    /**
     * @param AmqpMessage $message
     * @return \Generator
     * @throws BindingResolutionException
     */
    public function make(AmqpMessage $message)
    {
        foreach ($this->getListeners($message) as $listener => $listeners) {
            foreach ($listeners as $callback) {
                yield new Job(
                    $this->context,
                    $this->consumer,
                    $message,
                    $callback,
                    $listener
                );
            }
        }
    }

    /**
     * @param AmqpMessage $message
     * @return array
     * @throws BindingResolutionException
     */
    protected function getListeners(AmqpMessage $message)
    {
        return $this->container->make('broadcast.events')
            ->getListeners($message->getRoutingKey());
    }
}
