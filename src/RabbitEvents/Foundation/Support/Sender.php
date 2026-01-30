<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Support;

use RabbitEvents\Foundation\Contracts\Producer;
use RabbitEvents\Foundation\Contracts\Destination;
use RabbitEvents\Foundation\Contracts\Transport;
use RabbitEvents\Foundation\Message;

class Sender implements Transport
{
    public function __construct(protected Destination $destination, protected Producer $producer)
    {
    }

    public function send(Message $message): void
    {
        $this->producer->send($this->destination, $message->transportMessage());
    }
}
