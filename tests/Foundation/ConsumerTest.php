<?php

namespace RabbitEvents\Tests\Foundation;

use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\AmqpMessage;
use Interop\Amqp\Impl\AmqpMessage as ImplAmqpMessage;
use RabbitEvents\Foundation\Connection;
use RabbitEvents\Foundation\Consumer;
use RabbitEvents\Foundation\Context;
use RabbitEvents\Foundation\Contracts\Transport;
use RabbitEvents\Foundation\Message;
use \Mockery as m;

class ConsumerTest extends TestCase
{

    public function testNextMessage(): void
    {
        $context = new Context(m::mock(Connection::class));
        $context->setTransport(m::mock(Transport::class));

        $event = 'item.created';
        $payload = '{"pay":"load"}';

        $amqpMessage = new ImplAmqpMessage();
        $amqpMessage->setRoutingKey($event);
        $amqpMessage->setBody($payload);

        $amqpConsumer = m::mock(AmqpConsumer::class);
        $amqpConsumer->shouldReceive('receive')
            ->andReturn($amqpMessage);

        $consumer = new Consumer($amqpConsumer, $context);
        $message = $consumer->nextMessage();

        self::assertInstanceOf(Message::class, $message);
        self::assertEquals($event, $message->event());
        self::assertEquals($payload, $message->payload()->jsonSerialize());
    }

    public function testNoMessage()
    {
        $amqpConsumer = m::mock(AmqpConsumer::class);
        $amqpConsumer->shouldReceive('receive')->andReturnNull();

        $consumer = new Consumer($amqpConsumer, m::mock(Context::class));

        self::assertNull($consumer->nextMessage());
    }

    public function testAcknowledge(): void
    {
        $amqpConsumer = m::mock(AmqpConsumer::class);
        $amqpConsumer->shouldReceive('acknowledge')->once();

        $message = m::mock(Message::class)->makePartial();
        $message->shouldReceive('amqpMessage')
            ->andReturn(m::mock(AmqpMessage::class));

        $consumer = new Consumer($amqpConsumer, m::mock(Context::class));
        $consumer->acknowledge($message);
    }
}
