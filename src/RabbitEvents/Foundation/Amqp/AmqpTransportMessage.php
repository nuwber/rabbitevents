<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Amqp;

use Interop\Amqp\AmqpMessage;
use RabbitEvents\Foundation\Contracts\TransportMessage;

class AmqpTransportMessage implements TransportMessage
{
    public function __construct(private AmqpMessage $message)
    {
    }

    public function getBody(): string
    {
        return $this->message->getBody();
    }

    public function getProperty(string $name, mixed $default = null): mixed
    {
        return $this->message->getProperty($name, $default);
    }

    public function getProperties(): array
    {
        return $this->message->getProperties();
    }
}
