<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Amqp;

use Interop\Queue\Destination as InteropDestination;
use RabbitEvents\Foundation\Contracts\Destination;

class AmqpDestinationAdapter implements Destination
{
    public function __construct(private InteropDestination $destination)
    {
    }

    public function getOrigin(): InteropDestination
    {
        return $this->destination;
    }

    public function __call(string $method, array $args)
    {
        return $this->destination->$method(...$args);
    }
}
