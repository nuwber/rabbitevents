<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Contracts;

interface TransportMessageFactory
{
    public function make(string $event, Payload $payload, array $properties = []): TransportMessage;
}
