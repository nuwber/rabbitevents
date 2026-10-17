<?php

namespace RabbitEvents\Tests\Foundation\Stubs;

use RabbitEvents\Foundation\Contracts\Destination;

class DestinationStub implements Destination
{
    public function __construct(private ?string $name = null, private $origin = null)
    {
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getOrigin(): mixed
    {
        return $this->origin ?? $this;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}
