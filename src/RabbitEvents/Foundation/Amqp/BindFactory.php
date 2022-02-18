<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Amqp;

use Interop\Amqp\AmqpQueue;
use Interop\Amqp\Impl\AmqpBind;
use RabbitEvents\Foundation\Context;

class BindFactory
{
    public function __construct(private Context $context)
    {
    }

    /**
     * @param AmqpQueue $queue
     * @param string $event
     * @return AmqpBind
     */
    public function make(AmqpQueue $queue, string $event): AmqpBind
    {
        return new AmqpBind($this->context->destination(), $queue, $event);
    }
}
