<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Serialization;

use RabbitEvents\Foundation\Contracts\TransportMessage;
use Google\Protobuf\Internal\Message;
use RabbitEvents\Foundation\Contracts\Serializer;
use RabbitEvents\Foundation\Contracts\Payload;
use RabbitEvents\Foundation\Support\ProtobufPayload;

class ProtobufSerializer implements Serializer
{
    /**
     * @inheritDoc
     */
    public function serialize(mixed $payload): Payload
    {
        if (!$payload instanceof Message) {
            throw new \InvalidArgumentException('Payload must be an instance of Google\Protobuf\Internal\Message');
        }

        return new ProtobufPayload($payload);
    }

    /**
     * @inheritDoc
     */
    public function deserialize(TransportMessage $message): Payload
    {
        $class = $message->getProperty('type');

        if ($class && class_exists($class) && is_subclass_of($class, Message::class)) {
            /** @var Message $pbMessage */
            $pbMessage = new $class();
            $pbMessage->mergeFromString($message->getBody());

            return new ProtobufPayload($pbMessage);
        }

        throw new \RuntimeException('Generic deserialization for Protobuf is not supported without "type" property with valid class name.');
    }

    public function contentType(): string
    {
        return 'application/x-protobuf';
    }
}
