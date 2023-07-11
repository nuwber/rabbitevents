<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Amqp;

use Interop\Amqp\AmqpDestination;
use Interop\Amqp\AmqpTopic;
use RabbitEvents\Foundation\Context;

class DestinationTopicFactory
{
    public function __construct(private readonly Context $context)
    {
    }

    public function makeAndDeclare(string $name): AmqpTopic
    {
        $topic = $this->context->createTopic($name);

        $topic->setType(AmqpTopic::TYPE_TOPIC);
        $topic->addFlag(AmqpDestination::FLAG_DURABLE);

        $this->context->declareTopic($topic);

        return $topic;
    }
}
