<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Amqp;

use Interop\Amqp\AmqpDestination;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\Impl\AmqpBind;
use RabbitEvents\Foundation\Amqp\Connection;

class QueueFactory
{
    public function __construct(private readonly AmqpContext $context, private readonly Connection $connection)
    {
    }

    /**
     * @param string $queueName
     * @return AmqpQueue
     */
    public function makeAndDeclare(string $queueName): AmqpQueue
    {
        $queue = $this->context->createQueue($queueName);

        if ($this->connection->getConfig('durable', true)) {
            $queue->addFlag(AmqpDestination::FLAG_DURABLE);
        }

        if ($this->connection->getConfig('dlx.enabled', true) && !str_ends_with($queueName, '.failed')) {
            $dlxExchangeName = (string) $this->connection->getConfig('dlx.exchange', 'rabbitevents.dlx');
            $dlqQueueName = "{$queueName}.failed";

            $dlx = $this->context->createTopic($dlxExchangeName);
            $dlx->setType(AmqpTopic::TYPE_TOPIC);
            if ($this->connection->getConfig('durable', true)) {
                $dlx->addFlag(AmqpDestination::FLAG_DURABLE);
            }
            $this->context->declareTopic($dlx);

            $dlq = $this->context->createQueue($dlqQueueName);
            if ($this->connection->getConfig('durable', true)) {
                $dlq->addFlag(AmqpDestination::FLAG_DURABLE);
            }
            $this->context->declareQueue($dlq);

            $this->context->bind(new AmqpBind($dlx, $dlq, $queueName));

            $queue->setArgument('x-dead-letter-exchange', $dlxExchangeName);
            $queue->setArgument('x-dead-letter-routing-key', $queueName);
        }

        $this->context->declareQueue($queue);

        return $queue;
    }
}
