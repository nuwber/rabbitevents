<?php

namespace RabbitEvents\Tests\Foundation;

use Interop\Amqp\AmqpMessage;
use RabbitEvents\Foundation\Contracts\Transport;
use RabbitEvents\Foundation\Message;
use RabbitEvents\Foundation\Support\Payload;
use Mockery as m;

class MessageTest extends TestCase
{

    public function testAmqpMessage()
    {
        $message = new Message('item.created', new Payload([]));

        self::assertInstanceOf(AmqpMessage::class, $message->amqpMessage());

        $amqpMessage = new \Interop\Amqp\Impl\AmqpMessage();

        self::assertNotSame($amqpMessage, $message->amqpMessage());

        $message->setAmqpMessage($amqpMessage);

        self::assertSame($amqpMessage, $message->amqpMessage());
    }

    public function testIncreaseAttempts()
    {
        $message = new Message('item.created', new Payload([]));

        $amqpMessage = new \Interop\Amqp\Impl\AmqpMessage();

        $message->setAmqpMessage($amqpMessage);

        self::assertEquals(0, $message->attempts());

        $message->increaseAttempts();

        self::assertEquals(1, $message->attempts());
    }

    public function testCreateFromAmqpMessage()
    {
        $payload = ['pay' => 'load'];

        $amqpMessage = new \Interop\Amqp\Impl\AmqpMessage();
        $amqpMessage->setRoutingKey($event = 'item.created');
        $amqpMessage->setBody(json_encode($payload));

        $message = Message::createFromAmqpMessage($amqpMessage);

        self::assertEquals($event, $message->event());
        self::assertEquals($payload, $message->payload()->getPayload());
    }
}
