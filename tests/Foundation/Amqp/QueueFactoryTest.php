<?php

namespace RabbitEvents\Tests\Foundation\Amqp;

use Interop\Amqp\AmqpContext;
use Interop\Amqp\Impl\AmqpQueue;
use Interop\Amqp\AmqpDestination;
use RabbitEvents\Foundation\Amqp\Connection;
use RabbitEvents\Foundation\Amqp\QueueFactory;
use RabbitEvents\Foundation\Context;
use RabbitEvents\Listener\QueueName;
use RabbitEvents\Tests\Foundation\TestCase;

class QueueFactoryTest extends TestCase
{
    private array $events = ['item.created', 'item.updated'];

    public function test_make_queue_durable_by_default()
    {
        $resolvedQueueName = QueueName::resolve('rabbitevents-app', $this->events);
        $amqpQueue = new AmqpQueue($resolvedQueueName);
        
        $amqpContext = \Mockery::mock(AmqpContext::class);
        $amqpContext->shouldReceive('createQueue')->with($resolvedQueueName)->andReturn($amqpQueue);
        $amqpContext->shouldReceive('declareQueue')->with($amqpQueue);

        $connection = \Mockery::mock(Connection::class);
        $connection->shouldReceive('getConfig')->with('durable', true)->andReturn(true);

        $factory = new QueueFactory($amqpContext, $connection);

        $queue = $factory->makeAndDeclare($resolvedQueueName);

        self::assertInstanceOf(AmqpQueue::class, $queue);
        self::assertEquals(AmqpDestination::FLAG_DURABLE, $queue->getFlags());
        self::assertSame($amqpQueue, $queue);
    }

    public function test_make_queue_transient()
    {
        $resolvedQueueName = QueueName::resolve('rabbitevents-app', $this->events);
        $amqpQueue = new AmqpQueue($resolvedQueueName);
        
        $amqpContext = \Mockery::mock(AmqpContext::class);
        $amqpContext->shouldReceive('createQueue')->with($resolvedQueueName)->andReturn($amqpQueue);
        $amqpContext->shouldReceive('declareQueue')->with($amqpQueue);

        $connection = \Mockery::mock(Connection::class);
        $connection->shouldReceive('getConfig')->with('durable', true)->andReturn(false);

        $factory = new QueueFactory($amqpContext, $connection);

        $queue = $factory->makeAndDeclare($resolvedQueueName);

        self::assertEquals(AmqpDestination::FLAG_NOPARAM, $queue->getFlags());
    }
}
