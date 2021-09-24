<?php

namespace Nuwber\Events\Amqp;

use Interop\Amqp\AmqpQueue;
use Interop\Queue\Exception\Exception;
use Nuwber\Events\Queue\Context;

class QueueFactory
{
    /**
     * @var Context
     */
    private $context;

    /**
     * @var string
     */
    private $service;
    /**
     * @var BindFactory
     */
    private $bindFactory;

    public function __construct(Context $context, BindFactory $bindFactory, string $service)
    {
        $this->context = $context;
        $this->bindFactory = $bindFactory;
        $this->service = $service;
    }

    /**
     * @param string[] $event
     * @return AmqpQueue
     * @throws Exception
     */
    public function make(array $events): AmqpQueue
    {
        $queue = $this->context->createQueue($this->queueName($events));
        $queue->addFlag(AmqpQueue::FLAG_DURABLE);

        $this->context->declareQueue($queue);

        foreach ($events as $event) {
            $this->bind($queue, $event);
        }

        return $queue;
    }

    /**
     * @param string[] $events
     * @return string
     */
    protected function queueName(array $events): string
    {
        $keys = implode(':', $events);

        return "{$this->service}:{$keys}";
    }

    /**
     * @param AmqpQueue $queue
     * @param string $event
     * @throws Exception
     */
    protected function bind(AmqpQueue $queue, string $event): void
    {
        $bind = $this->bindFactory->make($queue, $event);

        $this->context->bind($bind);
    }
}
