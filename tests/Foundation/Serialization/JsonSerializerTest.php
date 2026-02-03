<?php

namespace RabbitEvents\Tests\Foundation\Serialization;

use Interop\Amqp\Impl\AmqpMessage;
use RabbitEvents\Foundation\Contracts\Payload;
use RabbitEvents\Foundation\Serialization\JsonSerializer;
use RabbitEvents\Foundation\Amqp\AmqpTransportMessage;
use RabbitEvents\Tests\Foundation\TestCase;

class JsonSerializerTest extends TestCase
{
    public function testSerialize(): void
    {
        $serializer = new JsonSerializer();
        $payload = $serializer->serialize(['foo' => 'bar']);

        self::assertInstanceOf(Payload::class, $payload);
        self::assertSame(json_encode(['foo' => 'bar']), $payload->serialize());
    }



    public function testDeserialize(): void
    {
        $serializer = new JsonSerializer();
        
        $message = new AmqpMessage();
        $message->setBody(json_encode(['foo' => 'bar']));
        
        $payload = $serializer->deserialize(new AmqpTransportMessage($message));

        self::assertInstanceOf(Payload::class, $payload);
        self::assertSame(['foo' => 'bar'], $payload->value());
    }
}
