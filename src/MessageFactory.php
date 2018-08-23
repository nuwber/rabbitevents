<?php

namespace Nuwber\Events;

use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpTopic;
use Interop\Queue\PsrContext;
use Interop\Queue\PsrTopic;

class MessageFactory
{
    /**
     * @var AmqpContext
     */
    private $context;
    /**
     * @var AmqpTopic
     */
    private $topic;

    public function __construct(PsrContext $context, PsrTopic $topic)
    {
        $this->context = $context;
        $this->topic = $topic;
    }

    /**
     * @param string $event
     * @param array $payload
     * @return \Interop\Amqp\AmqpMessage
     */
    public function make(string $event, array $payload)
    {
        $message = $this->context->createMessage(json_encode($payload, JSON_UNESCAPED_UNICODE));
        $message->setRoutingKey($event);

        return $message;
    }
}
