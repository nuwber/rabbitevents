<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Amqp;

use Interop\Amqp\AmqpDestination;
use Interop\Amqp\AmqpQueue;
use RabbitEvents\Foundation\Context;
use RabbitEvents\Foundation\Contracts\QueueNameInterface;

class QueueFactory
{
    public function __construct(private Context $context)
    {
    }

    /**
     * @param QueueNameInterface $queueName
     * @return AmqpQueue
     */
    public function make(QueueNameInterface $queueName): AmqpQueue
    {
        $queue = $this->context->createQueue($queueName->resolve());

        $queue->addFlag(AmqpDestination::FLAG_DURABLE);

        $this->context->declareQueue($queue);

        return $queue;
    }
}
