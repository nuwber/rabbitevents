<?php

namespace RabbitEvents\Tests\Foundation;


use Interop\Amqp\AmqpMessage;
use RabbitEvents\Foundation\Contracts\Transport;
use RabbitEvents\Foundation\Message;
use RabbitEvents\Foundation\Support\JsonPayload;
use Mockery as m;

class MessageTest extends TestCase
{

    public function testAmqpMessage()
    {
        $message = new Message('item.created', new JsonPayload([]));

        self::assertInstanceOf(\RabbitEvents\Foundation\Contracts\TransportMessage::class, $message->transportMessage());
        self::assertInstanceOf(AmqpMessage::class, $message->transportMessage()->getOrigin());

        $amqpMessage = new \Interop\Amqp\Impl\AmqpMessage();
        $transportMessage = new \RabbitEvents\Foundation\Amqp\AmqpTransportMessage($amqpMessage);

        self::assertNotSame($transportMessage, $message->transportMessage());

        $message->setTransportMessage($transportMessage);

        self::assertSame($transportMessage, $message->transportMessage());
        self::assertSame($amqpMessage, $message->transportMessage()->getOrigin());
    }

    public function testIncreaseAttempts()
    {
        $message = new Message('item.created', new JsonPayload([]));

        $transportMessageStub = new \RabbitEvents\Tests\Foundation\Stubs\TransportMessageStub();
        // Since stub properties are empty, default attempts 0.
        
        $message->setTransportMessage($transportMessageStub);

        self::assertEquals(0, $message->attempts());

        $message->increaseAttempts();

        self::assertEquals(1, $message->attempts());
    }

    public function testCreateFromTransportMessage()
    {
        $payload = ['pay' => 'load'];

        $amqpMessage = new \Interop\Amqp\Impl\AmqpMessage();
        $amqpMessage->setRoutingKey($event = 'item.created');
        $amqpMessage->setBody(json_encode($payload));
        
        $transportMessage = new \RabbitEvents\Foundation\Amqp\AmqpTransportMessage($amqpMessage);

        $message = Message::createFromTransportMessage($transportMessage);

        self::assertEquals($event, $message->event);
        self::assertEquals($payload, $message->payload->value());
    }
}
