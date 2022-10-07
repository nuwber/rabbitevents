<?php

declare(strict_types=1);

namespace RabbitEvents\Publisher;

use Illuminate\Support\Carbon;
use RabbitEvents\Foundation\Support\Payload;
use RabbitEvents\Foundation\Message;

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

        return $message;
    }
}
