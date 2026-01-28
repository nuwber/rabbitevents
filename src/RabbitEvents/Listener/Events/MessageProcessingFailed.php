<?php

declare(strict_types=1);

namespace RabbitEvents\Listener\Events;

use RabbitEvents\Foundation\Message;

class MessageProcessingFailed
{
    public function __construct(public readonly Message $message, public readonly \Throwable $exception)
    {
    }
}
