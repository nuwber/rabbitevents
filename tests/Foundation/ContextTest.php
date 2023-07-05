<?php

namespace RabbitEvents\Tests\Foundation;

use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\Impl\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use RabbitEvents\Foundation\Connection;
use RabbitEvents\Foundation\Consumer;
use RabbitEvents\Foundation\Context;
use Mockery as m;
use RabbitEvents\Foundation\Support\EnqueueOptions;

class ContextTest extends TestCase
{
    public function test_context_call()
    {
        $amqpContext = m::mock(AmqpContext::class);
        $amqpContext->shouldReceive()
            ->foo('bar')
            ->once()
            ->andReturn('result');

        $connection = m::mock(Connection::class);
        $connection->shouldReceive('createContext')
            ->andReturn($amqpContext);

        $context = new Context($connection);

        self::assertEquals('result', $context->foo('bar'));
    }

    public function test_create_consumer()
    {
        $amqpContext = m::mock(AmqpContext::class)->makePartial();
        $amqpContext->shouldReceive('createConsumer')
            ->andReturn(m::mock(AmqpConsumer::class));

        $amqpQueue = new AmqpQueue('name');

        $connection = m::mock(Connection::class);
        $connection->shouldReceive('createContext')
            ->andReturn($amqpContext);

        $context = new Context($connection);
        $consumer = $context->makeConsumer($amqpQueue);

        self::assertInstanceOf(Consumer::class, $consumer);
    }

    public function test_make_queue()
    {
        $events = ['event.one', 'event.two'];
        $enqueueOptions = new EnqueueOptions('test-app', $events);

        $amqpContext = m::mock(AmqpContext::class);
        $amqpContext->shouldReceive('bind')
            ->twice();
        $amqpContext->shouldReceive('createQueue')
            ->andReturn($amqpQueue = new AmqpQueue($enqueueOptions->name));
        $amqpContext->shouldReceive('declareQueue')
            ->once();

        $topic = m::mock(AmqpTopic::class);

        $connection = m::mock(Connection::class);
        $connection->shouldReceive()
            ->createContext()
            ->andReturn($amqpContext);


        $context = new Context($connection);
        $queue = $context->makeQueue($topic, $enqueueOptions);

        self::assertInstanceOf(AmqpQueue::class, $queue);
    }
}
