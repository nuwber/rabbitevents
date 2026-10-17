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
            array_merge($properties, [
                'content_type' => (string) $payload->contentType(),
                'content_encoding' => 'UTF-8',
            ])
        );
        $message->setRoutingKey($event);
        $message->setProperty('event', $event);

        if (!empty($properties['headers']) && is_array($properties['headers'])) {
            foreach ($properties['headers'] as $headerKey => $headerVal) {
                $message->setProperty((string) $headerKey, $headerVal);
            }
        }

        if (!empty($properties['message_id'])) {
            $message->setMessageId((string) $properties['message_id']);
        }

        $message->setDeliveryMode(AmqpMessage::DELIVERY_MODE_PERSISTENT);

        return new AmqpTransportMessage($message);
    }
}
