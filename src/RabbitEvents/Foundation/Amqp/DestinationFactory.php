<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Amqp;

use Interop\Amqp\AmqpDestination;
use Interop\Amqp\AmqpTopic;
use RabbitEvents\Foundation\Context;

class DestinationFactory
{
    protected const DEFAULT_EXCHANGE_NAME = 'events';

    public function __construct(private Context $context)
    {
    }

    public function make(?string $exchange = ''): AmqpTopic
    {
        $topic = $this->context->createTopic($exchange ?: self::DEFAULT_EXCHANGE_NAME);
        $topic->setType(AmqpTopic::TYPE_TOPIC);
        $topic->addFlag(AmqpDestination::FLAG_DURABLE);

        $this->context->declareTopic($topic);

        return $topic;
    }
}
