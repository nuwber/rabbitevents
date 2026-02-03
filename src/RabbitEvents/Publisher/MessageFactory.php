<?php

declare(strict_types=1);

namespace RabbitEvents\Publisher;

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
        $serializer = $this->registry->resolve($rawPayload);
        $payload = $serializer->serialize($rawPayload);
        
        $properties = [
            'content_type' => (string) $payload->contentType(),
        ];

        if (is_object($rawPayload)) {
            $properties['type'] = get_class($rawPayload);
        }

        $message = new Message($event->publishEventKey(), $payload, $properties);
        $message->setTimestamp(Carbon::now()->getTimestamp());

        return $message;
    }
}
