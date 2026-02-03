<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation;

use RabbitEvents\Foundation\Amqp\AmqpMessageFactory;
use RabbitEvents\Foundation\Contracts\Payload;
use RabbitEvents\Foundation\Contracts\TransportMessage;
use RabbitEvents\Foundation\Contracts\TransportMessageFactory;

class MessageFactory
{
    private static ?TransportMessageFactory $factory = null;

    public static function make(string $event, Payload $payload, array $properties = []): TransportMessage
    {
        return self::getFactory()->make($event, $payload, $properties);
    }

    public static function setFactory(TransportMessageFactory $factory): void
    {
        self::$factory = $factory;
    }

    private static function getFactory(): TransportMessageFactory
    {
        if (self::$factory === null) {
            self::$factory = new AmqpMessageFactory();
        }

        return self::$factory;
    }
}
