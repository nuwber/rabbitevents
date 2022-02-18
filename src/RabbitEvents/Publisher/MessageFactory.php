<?php

declare(strict_types=1);

namespace RabbitEvents\Publisher;

use RabbitEvents\Foundation\Support\Payload;
use RabbitEvents\Foundation\Contracts\Transport;
use RabbitEvents\Foundation\Message;

class MessageFactory
{
    public function __construct(private Transport $transport)
    {
    }

    public function make(ShouldPublish $event): Message
    {
        $payload = $event->toPublish();

        if (!$payload instanceof \JsonSerializable) {
            $payload = new Payload($payload);
        }

        return new Message($event->publishEventKey(), $payload, $this->transport);
    }
}
