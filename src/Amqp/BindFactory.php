<?php

namespace Nuwber\Events\Amqp;

use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpBind;
use Nuwber\Events\Queue\Context;

class BindFactory
{
    /**
     * @var Context
     */
    private $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    public function make(AmqpQueue $queue, string $routingKey): AmqpBind
    {
        return new AmqpBind($this->context->topic(), $queue, $routingKey);
    }
}
