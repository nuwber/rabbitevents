<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Amqp;

use Interop\Amqp\Impl\AmqpMessage;

class MessageFactory
{
    public static function make(string $event, \JsonSerializable $payload, array $properties = []): AmqpMessage
    {
        $message = new AmqpMessage(
            $payload->jsonSerialize(),
            $properties,
            [
                'content_type' => 'application/json',
                'content_encoding' => 'UTF-8',
            ]
        );
        $message->setRoutingKey($event);

        return $message;
    }
}
