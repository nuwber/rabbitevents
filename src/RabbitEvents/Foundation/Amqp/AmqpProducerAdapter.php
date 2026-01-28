<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Amqp;

use Interop\Amqp\AmqpProducer;
use RabbitEvents\Foundation\Contracts\Destination;
use RabbitEvents\Foundation\Contracts\Producer;
use RabbitEvents\Foundation\Contracts\TransportMessage;

class AmqpProducerAdapter implements Producer
{
    public function __construct(private AmqpProducer $producer)
    {
    }

    public function send(Destination $destination, TransportMessage $message): void
    {
        $this->producer->send($destination->getOrigin(), $message->getOrigin());
    }

    public function __call(string $method, array $args)
    {
        return $this->producer->$method(...$args);
    }
}
