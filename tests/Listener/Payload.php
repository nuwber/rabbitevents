<?php

namespace RabbitEvents\Tests\Listener;

class Payload implements \RabbitEvents\Foundation\Contracts\Payload
{
    public function __construct(private mixed $payload)
    {
    }

    public function value(): mixed
    {
        return $this->payload;
    }

    public function serialize(): string
    {
        if (is_string($this->payload)) {
            return $this->payload;
        }

        return json_encode($this->payload);
    }

    public function contentType(): string
    {
        return 'application/json';
    }
}
