<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Amqp;

use Interop\Amqp\AmqpDestination;
use Interop\Amqp\AmqpTopic;
use RabbitEvents\Foundation\Context;

class TopicDestinationFactory
{
    public function __construct(private Context $context)
    {
    }

    public function make(): AmqpTopic
    {
        $topic = $this->context->createTopic(
            $this->context->connection()->getConfig('exchange')
        );
        $topic->setType(AmqpTopic::TYPE_TOPIC);
        $topic->addFlag(AmqpDestination::FLAG_DURABLE);

        $this->context->declareTopic($topic);

        return $topic;
    }
}
