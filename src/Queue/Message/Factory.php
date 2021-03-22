<?php

namespace Nuwber\Events\Queue\Message;

use Interop\Amqp\Impl\AmqpMessage;

class Factory
{
    public static function make($event, $payload): AmqpMessage
    {
        $payload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $message = new AmqpMessage($payload);
        $message->setRoutingKey($event);

        return $message;
    }
}
