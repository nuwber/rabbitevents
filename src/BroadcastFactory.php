<?php

namespace Nuwber\Events;

use Interop\Amqp\Impl\AmqpMessage;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\Impl\AmqpBind;
use Interop\Queue\PsrContext;
use Interop\Queue\PsrTopic;

class BroadcastFactory
{
    /**
     * @var PsrTopic
     */
    private $topic;

    /**
     * @var \Enqueue\AmqpLib\AmqpProducer
     */
    private $producer;

    /**
     * @var AmqpContext
     */
    private $context;

    public function __construct(PsrContext $context, PsrTopic $topic)
    {
        $this->topic = $topic;
        $this->context = $context;
        $this->producer = $context->createProducer();
    }

    /**
     * Sends event message to queue
     *
     * @param AmqpMessage $message
     */
    public function send(AmqpMessage $message)
    {
        $this->producer->send($this->topic, $message);
    }

    /**
     * Bind queue to concrete event
     *
     * @param $event
     *
     * @return $this
     */
    protected function bind($event, $queue)
    {
        $this->context->bind(new AmqpBind($this->topic, $queue, $event));

        return $this;
    }
}
