<?php

namespace Nuwber\Events;

use Interop\Amqp\Impl\AmqpMessage;
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

    public function __construct(PsrContext $context, PsrTopic $topic)
    {
        $this->topic = $topic;
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
}
