<?php

declare(strict_types=1);

namespace RabbitEvents\Publisher;

use Google\Protobuf\Internal\Message as ProtobufMessage;
use Illuminate\Support\Carbon;
use RabbitEvents\Foundation\Message;
use RabbitEvents\Foundation\Serialization\SerializerRegistry;

class MessageFactory
{
    public function __construct(private SerializerRegistry $registry)
    {
    }

    public function make(ShouldPublish $event): Message
    {
        $rawPayload = $event->toPublish();
        $contentType = 'application/json';
        $properties = [];

        if ($rawPayload instanceof ProtobufMessage) {
            $contentType = 'application/x-protobuf';
            $properties['type'] = get_class($rawPayload);
        }

        $serializer = $this->registry->get($contentType);
        $payload = $serializer->serialize($rawPayload);

        $message = new Message($event->publishEventKey(), $payload, $properties);
        $message->setTimestamp(Carbon::now()->getTimestamp());

        return $message;
    }
}
