<?php

namespace Nuwber\Events;

use Interop\Amqp\AmqpTopic;
use Interop\Amqp\AmqpContext;

class Publisher
{
    /**
     * @var AmqpTopic
     */
    private $topic;

    /**
     * @var AmqpContext
     */
    private $context;

    public function __construct(AmqpContext $context, AmqpTopic $topic)
    {
        $this->context = $context;
        $this->topic = $topic;
    }

    /**
     * Publishes payload
     *
     * @param string $event
     * @param array $payload
     * @throws \Interop\Queue\Exception
     * @throws \Interop\Queue\Exception\InvalidDestinationException
     * @throws \Interop\Queue\Exception\InvalidMessageException
     */
    public function send(string $event, array $payload)
    {
        $message = $this->context->createMessage(json_encode($payload, JSON_UNESCAPED_UNICODE));
        $message->setRoutingKey($event);

        $this->context->createProducer()->send($this->topic, $message);
    }
}
