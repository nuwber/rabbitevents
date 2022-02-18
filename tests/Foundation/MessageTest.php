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
        $message = new Message('item.created', new Payload([]), m::mock(Transport::class));

        self::assertInstanceOf(AmqpMessage::class, $message->amqpMessage());

        $amqpMessage = new \Interop\Amqp\Impl\AmqpMessage();

        self::assertNotSame($amqpMessage, $message->amqpMessage());

        $message->setAmqpMessage($amqpMessage);

        self::assertSame($amqpMessage, $message->amqpMessage());
    }

    public function testIncreaseAttempts()
    {
        $message = new Message('item.created', new Payload([]), m::mock(Transport::class));

        $amqpMessage = new \Interop\Amqp\Impl\AmqpMessage();

        $message->setAmqpMessage($amqpMessage);

        self::assertEquals(0, $message->attempts());

        $message->increaseAttempts();

        self::assertEquals(1, $message->attempts());
    }

    public function testSend()
    {
        $transport = m::spy(Transport::class);

        $message = new Message('item.created', new Payload([]), $transport);
        $message->send(100);

        $transport->shouldHaveReceived()
            ->send($message, 100);
    }

    public function testCreateFromAmqpMessage()
    {
        $payload = ['pay' => 'load'];

        $amqpMessage = new \Interop\Amqp\Impl\AmqpMessage();
        $amqpMessage->setRoutingKey($event = 'item.created');
        $amqpMessage->setBody(json_encode($payload));

        $message = Message::createFromAmqpMessage($amqpMessage, m::mock(Transport::class));

        self::assertEquals($event, $message->event());
        self::assertEquals($payload, $message->payload()->getPayload());
    }

    public function testRelease()
    {
        $amqpMessage = new \Interop\Amqp\Impl\AmqpMessage();
        $transport = m::spy(Transport::class);

        $message = new Message('item.created', new Payload([]), $transport);
        $message->setAmqpMessage($amqpMessage);

        self::assertSame($amqpMessage, $message->amqpMessage());

        $message->release(100);

        $transport->shouldHaveReceived()->send($message, 100);

        self::assertNotSame($amqpMessage, $message->amqpMessage());
    }
}
