<?php

declare(strict_types=1);

namespace App\Listeners;

use My\Protobuf\MessageClass;

class ProtobufListener
{
    /**
     * Handle the event.
     * 
     * @param MessageClass $message The payload is the Message object itself when using Protobuf.
     */
    public function handle(MessageClass $message): void
    {
        // $message is an instance of the Protobuf message class
        // passed from the event
        $id = $message->getId();
    }
}
