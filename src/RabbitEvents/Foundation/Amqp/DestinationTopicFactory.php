<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Amqp;

use Interop\Amqp\AmqpDestination;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\AmqpContext;

class DestinationTopicFactory
{
    public function __construct(private readonly AmqpContext $context, private readonly Connection $connection)
    {
    }

    public function makeAndDeclare(string $name): AmqpTopic
    {
        $topic = $this->context->createTopic($name);

        $topic->setType(AmqpTopic::TYPE_TOPIC);
        
        if ($this->connection->getConfig('durable', true)) {
           $topic->addFlag(AmqpDestination::FLAG_DURABLE);
        }

        $this->context->declareTopic($topic);

        return $topic;
    }
}
