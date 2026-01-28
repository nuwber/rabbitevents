<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Amqp;

use Interop\Amqp\Impl\AmqpMessage;
use RabbitEvents\Foundation\Contracts\Payload;
use RabbitEvents\Foundation\Contracts\TransportMessage;
use RabbitEvents\Foundation\Contracts\TransportMessageFactory;

class AmqpMessageFactory implements TransportMessageFactory
{
    public function make(string $event, Payload $payload, array $properties = []): TransportMessage
    {
        $message = new AmqpMessage(
            $payload->serialize(),
            $properties,
            [
                'content_type' => $payload->contentType(),
                'content_encoding' => 'UTF-8',
            ]
        );
        $message->setRoutingKey($event);
        $message->setProperty('event', $event);

        return new AmqpTransportMessage($message);
    }
}
