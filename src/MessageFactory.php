<?php

namespace Nuwber\Events;

use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpMessage;

class MessageFactory
{
    /**
     * @var AmqpContext
     */
    private $context;

    public function __construct(AmqpContext $context)
    {
        $this->context = $context;
    }

    /**
     * @param string $event
     * @param array $payload
     * @return AmqpMessage
     */
    public function make(string $event, array $payload): AmqpMessage
    {


        return $message;
    }
}
