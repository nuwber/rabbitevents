<?php

declare(strict_types=1);

namespace RabbitEvents\Publisher;

use Illuminate\Support\Carbon;
use RabbitEvents\Foundation\Message;
use RabbitEvents\Foundation\Serialization\SerializerRegistry;
use RabbitEvents\Foundation\Support\Uuid;

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
            'message_id' => Uuid::generate(),
        ];

        if (class_exists(\Illuminate\Support\Facades\Context::class) && \Illuminate\Support\Facades\Facade::getFacadeApplication()) {
            try {
                $contextData = \Illuminate\Support\Facades\Context::all();
                if (!empty($contextData)) {
                    $properties['headers']['_laravel_context'] = $contextData;
                }
            } catch (\Throwable) {
            }
        }

        if (is_object($rawPayload)) {
            $properties['type'] = get_class($rawPayload);
        }

        $message = new Message($event->publishEventKey(), $payload, $properties);
        $message->setTimestamp(Carbon::now()->getTimestamp());

        return $message;
    }
}
