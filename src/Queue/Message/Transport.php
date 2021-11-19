<?php

namespace Nuwber\Events\Queue\Message;

use Interop\Queue\Message;

interface Transport
{
    /**
     * @param Message $message
     * @param int $delay
     */
    public function send(Message $message, int $delay = 0): void;
}
