<?php

namespace Nuwber\Events\Queue\Jobs;

use Generator;
use Interop\Amqp\AmqpMessage;
use Nuwber\Events\Dispatcher;
use Nuwber\Events\Queue\Manager;
use Illuminate\Container\Container;

class Factory
{
    /**
     * @var Dispatcher
     */
    private $dispatcher;

    /**
     * @var Manager
     */
    private $queueManager;

    /**
     * @var Container
     */
    private $container;

    public function __construct(Container $container, Dispatcher $dispatcher, Manager $queueManager)
    {
        $this->container = $container;
        $this->dispatcher = $dispatcher;
        $this->queueManager = $queueManager;
    }

    /**
     * @param AmqpMessage $message
     * @return Generator
     */
    public function makeJobs(AmqpMessage $message): Generator
    {
        foreach ($this->getListeners($message) as $listener => $callbacks) {
            foreach ((array) $callbacks as $callback) {
                yield new Job($this->container, $this->queueManager, $message, $callback, $listener);
            }
        }
    }

    /**
     * @param AmqpMessage $message
     * @return array
     */
    private function getListeners(AmqpMessage $message): array
    {
        return $this->dispatcher->getListeners($message->getRoutingKey());
    }
}
