<?php

declare(strict_types=1);

namespace RabbitEvents\Publisher;

use Illuminate\Support\Carbon;
use PhpAmqpLib\Message\AMQPMessage;
use RabbitEvents\Foundation\Message;
use RabbitEvents\Foundation\Support\Payload;
use RabbitEvents\Publisher\Support\DurableMessageInterface;

class MessageFactory
{
    public function __construct()
    {
    }

    public function make(ShouldPublish $event): Message
    {
        $payload = $event->toPublish();

        if (!$payload instanceof \JsonSerializable) {
            $payload = new Payload($payload);
        }

        $message = new Message($event->publishEventKey(), $payload);
        $message->setTimestamp(Carbon::now()->getTimestamp());


        if ($event instanceof DurableMessageInterface) {
            $message->setDeliveryMode(AMQPMessage::DELIVERY_MODE_PERSISTENT);
        }
        if (property_exists($event, 'priority') && $event->priority > 0) {
            $message->setPriority($event->priority);
        }

        return $message;
    }
}
