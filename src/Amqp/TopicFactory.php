<?php

namespace Nuwber\Events\Amqp;

use Enqueue\AmqpLib\AmqpContext;
use Interop\Queue\Topic;
use Interop\Amqp\AmqpTopic;

class TopicFactory
{
    protected const DEFAULT_EXCHANGE_NAME = 'events';

    /**
     * @var AmqpContext
     */
    private $context;

    public function __construct(AmqpContext $context)
    {
        $this->context = $context;
    }

    public function make(?string $exchange = ''): Topic
    {
        $topic = $this->context->createTopic($exchange ?: self::DEFAULT_EXCHANGE_NAME);
        $topic->setType(AmqpTopic::TYPE_TOPIC);
        $topic->addFlag(AmqpTopic::FLAG_DURABLE);

        $this->context->declareTopic($topic);

        return $topic;
    }
}
