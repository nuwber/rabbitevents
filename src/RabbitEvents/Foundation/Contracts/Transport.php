<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Contracts;

use RabbitEvents\Foundation\Message;

interface Transport
{
    /**
     * @param Message $message
     * @param int $delay
     */
    public function send(Message $message, int $delay = 0): void;
}
