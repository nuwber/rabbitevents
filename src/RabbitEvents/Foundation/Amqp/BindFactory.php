<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Amqp;

use Interop\Amqp\AmqpDestination;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\Impl\AmqpBind;

class BindFactory
{
    /**
     * @param AmqpQueue $queue
     * @param string $event
     * @return AmqpBind
     */
    public function make(AmqpDestination $destination, AmqpQueue $queue, string $event): AmqpBind
    {
        return new AmqpBind($destination, $queue, $event);
    }
}
