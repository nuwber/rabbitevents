<?php

namespace RabbitEvents\Tests\Foundation\Amqp;

use Interop\Amqp\Impl\AmqpMessage;
use RabbitEvents\Foundation\Amqp\MessageFactory;
use RabbitEvents\Foundation\Contracts\Payload;
use RabbitEvents\Tests\Foundation\TestCase;

class MessageFactoryTest extends TestCase
{
    public function testMake()
    {
        $payload = new class implements Payload {

            public function jsonSerialize(): string
            {
                return json_encode($this->getPayload());
            }

            public function getPayload(): mixed
            {
                return ['some' => 'payload'];
            }
        };

        $result = MessageFactory::make('event', $payload, ['x-test' => 'property']);

        self::assertInstanceOf(AmqpMessage::class, $result);
        self::assertEquals('event', $result->getRoutingKey());
        self::assertEquals($payload->jsonSerialize(), $result->getBody());
        self::assertEquals('property', $result->getProperty('x-test'));

        self::assertEquals('UTF-8', $result->getContentEncoding());
        self::assertEquals('application/json', $result->getContentType());
    }
}
