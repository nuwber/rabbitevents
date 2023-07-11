<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Amqp;

use Interop\Amqp\AmqpDestination;
use Interop\Amqp\AmqpQueue;
use RabbitEvents\Foundation\Context;
use RabbitEvents\Foundation\Support\EnqueueOptions;

class QueueFactory
{
    public function __construct(private readonly Context $context)
    {
    }

    /**
     * @param EnqueueOptions $enqueueOptions
     * @return AmqpQueue
     */
    public function makeAndDeclare(EnqueueOptions $enqueueOptions): AmqpQueue
    {
        $queue = $this->context->createQueue($enqueueOptions->name);

        $queue->addFlag(AmqpDestination::FLAG_DURABLE);

        $this->context->declareQueue($queue);

        return $queue;
    }
}
