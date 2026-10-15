<?php

declare(strict_types=1);

namespace RabbitEvents\Publisher;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Str;
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
        
        $messageId = (string) Str::uuid();

        $properties = [
            'content_type' => (string) $payload->contentType(),
            'message_id' => $messageId,
            'headers' => [
                'x-deduplication-header' => $messageId,
            ],
        ];

        if (Facade::getFacadeApplication() && ($contextData = Context::all())) {
            $properties['headers']['trace_context'] = $contextData;
        }

        if (is_object($rawPayload)) {
            $properties['type'] = get_class($rawPayload);
        }

        $message = new Message($event->publishEventKey(), $payload, $properties);
        $message->setTimestamp(Carbon::now()->getTimestamp());

        return $message;
    }
}
