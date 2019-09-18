<?php

namespace Nuwber\Events\Queue;

use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpBind;

class ConsumerFactory
{
    /**
     * @var AmqpContext
     */
    private $context;

    /**
     * @var AmqpTopic
     */
    private $topic;

    public function __construct(AmqpContext $context, AmqpTopic $topic)
    {
        $this->context = $context;
        $this->topic = $topic;
    }

    /**
     * @param NameResolver $nameResolver
     * @return AmqpConsumer
     */
    public function make(NameResolver $nameResolver): AmqpConsumer
    {
        return $this->context->createConsumer($this->createQueue($nameResolver));
    }

    /**
     * @param NameResolver $nameResolver
     * @return AmqpQueue
     */
    protected function createQueue(NameResolver $nameResolver): AmqpQueue
    {
        $queue = $this->context->createQueue($nameResolver->queue());
        $queue->addFlag(AmqpQueue::FLAG_DURABLE);

        $this->context->declareQueue($queue);
        $this->context->bind(new AmqpBind($this->topic, $queue, $nameResolver->bind()));

        return $queue;
    }
}
