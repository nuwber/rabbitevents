<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Amqp;

use Interop\Amqp\AmqpDestination;
use Interop\Amqp\AmqpQueue;
use RabbitEvents\Foundation\Context;

class QueueFactory
{
    public function __construct(private readonly Context $context)
    {
    }

    /**
     * @param string $queueName
     * @return AmqpQueue
     */
    public function makeAndDeclare(string $queueName): AmqpQueue
    {
        $queue = $this->context->createQueue($queueName);

        $queue->addFlag(AmqpDestination::FLAG_DURABLE);

        $this->context->declareQueue($queue);

        return $queue;
    }
}
