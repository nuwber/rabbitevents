<?php

namespace RabbitEvents\Tests\Foundation;

use RabbitEvents\Foundation\Contracts\QueueConsumer;
use RabbitEvents\Foundation\Contracts\TransportMessage;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use RabbitEvents\Foundation\Consumer;
use RabbitEvents\Foundation\Context;
use RabbitEvents\Foundation\Exceptions\ConnectionLostException;
use RabbitEvents\Foundation\Message;
use RabbitEvents\Foundation\Serialization\SerializerRegistry;
use RabbitEvents\Foundation\Contracts\Serializer;
use RabbitEvents\Tests\Foundation\Stubs\QueueConsumerStub;
use RabbitEvents\Tests\Foundation\Stubs\TransportMessageStub;
use \Mockery as m;

class ConsumerTest extends TestCase
{
    public function testNextMessage(): void
    {
        $event = 'item.created';
        $payload = ['pay' => 'load'];

        $transportMessage = new TransportMessageStub(
            properties: ['event' => null, 'x-attempts' => 2, 'content_type' => 'application/json', 'routing_key' => $event]
        );

        $registry = m::mock(SerializerRegistry::class);
        $serializer = m::mock(Serializer::class);
        $serializer->shouldReceive('deserialize')
            ->with($transportMessage)
            ->andReturn(new \RabbitEvents\Foundation\Support\JsonPayload($payload));
        
        $registry->shouldReceive('get')->with('application/json')->andReturn($serializer);

        $consumerStub = new QueueConsumerStub([$transportMessage]);

        $consumer = new Consumer($consumerStub, $registry);
        $message = $consumer->nextMessage();

        self::assertInstanceOf(Message::class, $message);
        self::assertEquals($event, $message->event);
        self::assertEquals($payload, $message->payload->value()); 
        self::assertEquals(3, $message->attempts());
    }

    public function testNoMessage()
    {
        $consumerStub = new QueueConsumerStub([]);
        $consumer = new Consumer($consumerStub, m::mock(SerializerRegistry::class));

        self::assertNull($consumer->nextMessage());
    }

    public function testAcknowledge(): void
    {
        $transportMessage = new TransportMessageStub();
        
        $message = m::mock(Message::class)->makePartial();
        $message->shouldReceive('transportMessage')->andReturn($transportMessage);

        $consumerStub = new QueueConsumerStub();
        
        $consumer = new Consumer($consumerStub, m::mock(SerializerRegistry::class));
        $consumer->acknowledge($message);
        
        self::assertContains($transportMessage, $consumerStub->acknowledged);
    }

    public function testConnectionLostCatch()
    {
        $this->expectException(ConnectionLostException::class);

        $consumerMock = m::mock(QueueConsumer::class);
        $consumerMock->shouldReceive()
            ->receive(123)
            ->andThrow(AMQPRuntimeException::class);

        $consumer = new Consumer($consumerMock, m::mock(SerializerRegistry::class));
        $consumer->nextMessage(123);
    }
}
