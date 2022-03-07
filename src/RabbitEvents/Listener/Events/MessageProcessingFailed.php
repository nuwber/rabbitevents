<?php

namespace RabbitEvents\Listener\Events;

use RabbitEvents\Foundation\Message;

class MessageProcessingFailed
{
    public function __construct(public Message $message, public \Throwable $exception)
    {
    }
}
