<?php

namespace RabbitEvents\Tests\Listener;

class Payload implements \JsonSerializable
{
    public function __construct(private mixed $payload)
    {
    }

    public function jsonSerialize(): mixed
    {
        if (is_string($this->payload)) {
            return $this->payload;
        }

        return json_encode($this->payload);
    }
}
