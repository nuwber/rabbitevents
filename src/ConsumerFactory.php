<?php

namespace Nuwber\Events;

use Enqueue\AmqpLib\AmqpContext;
use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\Impl\AmqpBind;
use Interop\Amqp\Impl\AmqpQueue;
use Interop\Amqp\Impl\AmqpTopic;
use Interop\Queue\PsrContext;
use Interop\Queue\PsrTopic;

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

    /** @var AmqpConsumer */
    private $consumer;

    public function __construct(PsrContext $context, PsrTopic $topic)
    {
        $this->context = $context;
        $this->topic = $topic;
    }

    /**
     * @param NameResolver $nameResolver
     * @return AmqpConsumer
     * @throws \Interop\Queue\Exception
     */
    public function make(NameResolver $nameResolver): AmqpConsumer
    {
        if (!$this->consumer) {
            $this->consumer = $this->createConsumer($nameResolver);
        }

        return $this->consumer;
    }

    /**
     * Bind queue to concrete event.
     *
     * @param string $event
     * @param AmqpQueue $queue
     * @return $this
     * @throws \Interop\Queue\Exception
     */
    protected function bind(string $event, AmqpQueue $queue)
    {
        $this->context->bind(new AmqpBind($this->topic, $queue, $event));

        return $this;
    }

    /**
     * @param NameResolver $nameResolver
     * @return AmqpQueue|\Interop\Queue\PsrQueue
     */
    protected function createQueue(NameResolver $nameResolver)
    {
        $queue = $this->context->createQueue($nameResolver->queue());
        $queue->addFlag(AmqpQueue::FLAG_DURABLE);

        $this->context->declareQueue($queue);

        return $queue;
    }

    /**
     * @param NameResolver $nameResolver
     * @return AmqpConsumer
     * @throws \Interop\Queue\Exception
     */
    protected function createConsumer(NameResolver $nameResolver): AmqpConsumer
    {
        $queue = $this->createQueue($nameResolver);

        $this->bind($nameResolver->bind(), $queue);

        return $this->context->createConsumer($queue);
    }
}
