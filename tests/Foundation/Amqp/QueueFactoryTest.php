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

    public function test_make_queue_durable_with_dlx()
    {
        $resolvedQueueName = QueueName::resolve('rabbitevents-app', $this->events);
        $amqpQueue = new AmqpQueue($resolvedQueueName);
        $dlqQueue = new AmqpQueue("{$resolvedQueueName}.failed");
        $dlxTopic = new \Interop\Amqp\Impl\AmqpTopic('rabbitevents.dlx');
        
        $amqpContext = \Mockery::mock(AmqpContext::class);
        $amqpContext->shouldReceive('createQueue')->with($resolvedQueueName)->andReturn($amqpQueue);
        $amqpContext->shouldReceive('createQueue')->with("{$resolvedQueueName}.failed")->andReturn($dlqQueue);
        $amqpContext->shouldReceive('createTopic')->with('rabbitevents.dlx')->andReturn($dlxTopic);
        $amqpContext->shouldReceive('declareTopic')->with($dlxTopic)->once();
        $amqpContext->shouldReceive('declareQueue')->with($dlqQueue)->once();
        $amqpContext->shouldReceive('bind')->with(\Mockery::type(\Interop\Amqp\Impl\AmqpBind::class))->once();
        $amqpContext->shouldReceive('declareQueue')->with($amqpQueue)->once();

        $connection = \Mockery::mock(Connection::class);
        $connection->shouldReceive('getConfig')->with('durable', true)->andReturn(true);
        $connection->shouldReceive('getConfig')->with('dlx.enabled', true)->andReturn(true);
        $connection->shouldReceive('getConfig')->with('dlx.exchange', 'rabbitevents.dlx')->andReturn('rabbitevents.dlx');

        $factory = new QueueFactory($amqpContext, $connection);

        $queue = $factory->makeAndDeclare($resolvedQueueName);

        self::assertInstanceOf(AmqpQueue::class, $queue);
        self::assertEquals(AmqpDestination::FLAG_DURABLE, $queue->getFlags());
        self::assertEquals('rabbitevents.dlx', $queue->getArgument('x-dead-letter-exchange'));
        self::assertEquals($resolvedQueueName, $queue->getArgument('x-dead-letter-routing-key'));
    }

    public function test_make_queue_transient_without_dlx()
    {
        $resolvedQueueName = QueueName::resolve('rabbitevents-app', $this->events);
        $amqpQueue = new AmqpQueue($resolvedQueueName);
        
        $amqpContext = \Mockery::mock(AmqpContext::class);
        $amqpContext->shouldReceive('createQueue')->with($resolvedQueueName)->andReturn($amqpQueue);
        $amqpContext->shouldReceive('declareQueue')->with($amqpQueue)->once();

        $connection = \Mockery::mock(Connection::class);
        $connection->shouldReceive('getConfig')->with('durable', true)->andReturn(false);
        $connection->shouldReceive('getConfig')->with('dlx.enabled', true)->andReturn(false);

        $factory = new QueueFactory($amqpContext, $connection);

        $queue = $factory->makeAndDeclare($resolvedQueueName);

        self::assertEquals(AmqpDestination::FLAG_NOPARAM, $queue->getFlags());
        self::assertNull($queue->getArgument('x-dead-letter-exchange'));
    }

    public function test_make_failed_queue_does_not_declare_dlx()
    {
        $queueName = 'my-service:events.failed';
        $amqpQueue = new AmqpQueue($queueName);

        $amqpContext = \Mockery::mock(AmqpContext::class);
        $amqpContext->shouldReceive('createQueue')->with($queueName)->andReturn($amqpQueue);
        $amqpContext->shouldReceive('declareQueue')->with($amqpQueue)->once();

        $connection = \Mockery::mock(Connection::class);
        $connection->shouldReceive('getConfig')->with('durable', true)->andReturn(true);
        $connection->shouldReceive('getConfig')->with('dlx.enabled', true)->andReturn(true);

        $factory = new QueueFactory($amqpContext, $connection);

        $queue = $factory->makeAndDeclare($queueName);

        self::assertEquals(AmqpDestination::FLAG_DURABLE, $queue->getFlags());
        self::assertNull($queue->getArgument('x-dead-letter-exchange'));
    }
}
