<?php

namespace RabbitEvents\Tests\Foundation\Amqp;

use Interop\Amqp\AmqpDestination;
use Interop\Amqp\Impl\AmqpQueue as ImplAmqpQueue;
use RabbitEvents\Foundation\Amqp\QueueFactory;
use RabbitEvents\Foundation\Context;
use RabbitEvents\Tests\Foundation\TestCase;

class QueueFactoryTest extends TestCase
{
    private $event = 'item.created';

    public function testMake()
    {
        $queueName = "rabbitevents-app:{$this->event}";

        $amqpQueue = new ImplAmqpQueue($queueName);

        $context = \Mockery::mock(Context::class);
        $context->shouldReceive()
            ->createQueue($queueName)
            ->andReturn($amqpQueue);

        $context->shouldReceive()->declareQueue($amqpQueue);

        $factory = new QueueFactory($context);

        $queue = $factory->make($this->event);

        self::assertInstanceOf(ImplAmqpQueue::class, $queue);
        self::assertEquals(AmqpDestination::FLAG_DURABLE, $queue->getFlags());
        self::assertSame($amqpQueue, $queue);
        self::assertEquals($queueName, $queue->getQueueName());
    }
}
