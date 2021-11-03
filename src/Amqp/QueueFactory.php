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

    public function make(string $event, string $queueExplicitName = null, bool $passive = false): AmqpQueue
    {
        $queueName = $queueExplicitName ?? $this->generateQueueName($event);
        $queue = $this->context->createQueue($queueName);
        $queue->addFlag(AmqpQueue::FLAG_DURABLE);
        
        if($passive)
        {
            $queue->addFlag(AmqpQueue::FLAG_PASSIVE);
        }

        $this->context->declareQueue($queue);

        if(!$passive)
        {
            $this->bind($queue, $event);
        }
        return $queue;
    }

    /**
     * @param string $event
     * @return string
     */
    protected function generateQueueName(string $event): string
    {
        return "{$this->service}:$event";
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
