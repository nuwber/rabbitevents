<?php

namespace RabbitEvents\Tests\Foundation\Stubs;

use RabbitEvents\Foundation\Contracts\Destination;

class DestinationStub implements Destination
{
    public function __construct(private $origin = null)
    {
    }

    public function getOrigin(): mixed
    {
        return $this->origin ?? $this;
    }
}
