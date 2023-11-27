<?php

namespace RabbitEvents\Tests\Foundation\Amqp;

use Interop\Amqp\AmqpDestination;
use Interop\Amqp\Impl\AmqpQueue as ImplAmqpQueue;
use RabbitEvents\Foundation\Amqp\QueueFactory;
use RabbitEvents\Foundation\Context;
use RabbitEvents\Listener\QueueName;
use RabbitEvents\Tests\Foundation\TestCase;

class QueueFactoryTest extends TestCase
{
    private array $events = ['item.created', 'item.updated'];

    public function test_make_queue()
    {
        $resolvedQueueName = QueueName::resolve('rabbitevents-app', $this->events);

        $amqpQueue = new ImplAmqpQueue($resolvedQueueName);

        $context = \Mockery::mock(Context::class);
        $context->shouldReceive()
            ->createQueue($resolvedQueueName)
            ->andReturn($amqpQueue);

        $context->shouldReceive()->declareQueue($amqpQueue);

        $factory = new QueueFactory($context);

        $queue = $factory->makeAndDeclare($resolvedQueueName);

        self::assertInstanceOf(ImplAmqpQueue::class, $queue);
        self::assertEquals(AmqpDestination::FLAG_DURABLE, $queue->getFlags());
        self::assertSame($amqpQueue, $queue);
        self::assertEquals($resolvedQueueName, $queue->getQueueName());
    }
}
