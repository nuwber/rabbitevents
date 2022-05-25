<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Amqp;

use Interop\Amqp\AmqpDestination;
use Interop\Amqp\AmqpQueue;
use RabbitEvents\Foundation\Context;
use RabbitEvents\Foundation\Contracts\QueueName as QueueNameInterface;
use RabbitEvents\Foundation\Support\QueueName;

class QueueFactory
{
    public function __construct(private Context $context)
    {
    }

    /**
     * @param QueueNameInterface|string $queueName
     * @return AmqpQueue
     */
    public function make(QueueNameInterface|string $queueName): AmqpQueue
    {
        $queue = $this->context->createQueue($this->resolveName($queueName));

        $queue->addFlag(AmqpDestination::FLAG_DURABLE);

        $this->context->declareQueue($queue);

        return $queue;
    }

    protected function resolveName(QueueNameInterface|string $queueName): string
    {
        if (is_string($queueName)) {
            $queueName = new QueueName(env('APP_NAME', 'rabbitevents-app'), $queueName);
        }

        return $queueName->resolve();
    }
}
