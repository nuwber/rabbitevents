<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Contracts;

use RabbitEvents\Foundation\Message;

interface Transport
{
    /**
     * @param Message $message
     */
    public function send(Message $message): void;
}
