<?php

declare(strict_types=1);

namespace App\Events;

use Google\Protobuf\Internal\Message;
use RabbitEvents\Publisher\Support\AbstractPublishableEvent;

class ProtobufEvent extends AbstractPublishableEvent
{
    public function __construct(public readonly Message $message)
    {
    }

    public function publishEventKey(): string
    {
        return 'protobuf.event';
    }

    public function toPublish(): Message
    {
        return $this->message;
    }
}
