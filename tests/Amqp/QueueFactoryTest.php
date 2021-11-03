<?php

namespace Nuwber\Events\Tests\Amqp;

use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpBind;
use Interop\Amqp\Impl\AmqpQueue as ImplAmqpQueue;
use Nuwber\Events\Amqp\BindFactory;
use Nuwber\Events\Amqp\QueueFactory;
use Nuwber\Events\Queue\Context;
use Nuwber\Events\Tests\TestCase;

class QueueFactoryTest extends TestCase
{
    private $event = 'item.created';

    public function testMake()
    {
        $queueName = "test-app:{$this->event}";

        $amqpQueue = new ImplAmqpQueue($queueName);
        $amqpTopic = \Mockery::spy(AmqpTopic::class);

        $context = \Mockery::mock(Context::class, [\Mockery::mock(AmqpContext::class), $amqpTopic])->makePartial();
        $context->shouldReceive()
            ->createQueue($queueName)
            ->andReturn($amqpQueue);

        $context->shouldReceive()->declareQueue($amqpQueue);

        $context->shouldReceive('topic')
            ->andReturn($amqpTopic);

        $amqpBind = new AmqpBind($amqpTopic, $amqpQueue, $this->event);
        $bind = \Mockery::mock(BindFactory::class, [$context])->makePartial();
        $bind->shouldReceive()->make($amqpQueue, $this->event)
            ->andReturn($amqpBind);

        $context->shouldReceive()->bind($amqpBind);

        $factory = new QueueFactory($context, $bind, 'test-app');

        $queue = $factory->make($this->event);

        self::assertInstanceOf(AmqpQueue::class, $queue);
        self::assertEquals(AmqpQueue::FLAG_DURABLE, $queue->getFlags());
        self::assertSame($amqpQueue, $queue);
        self::assertEquals($queueName, $queue->getQueueName());
    }

    public function testMakePassive()
    {
        $queueName = "test-app:{$this->event}";

        $amqpQueue = new ImplAmqpQueue($queueName);
        $amqpTopic = \Mockery::spy(AmqpTopic::class);

        $context = \Mockery::mock(Context::class, [\Mockery::mock(AmqpContext::class), $amqpTopic])->makePartial();
        $context->shouldReceive()
            ->createQueue($queueName)
            ->andReturn($amqpQueue);

        $context->shouldReceive()->declareQueue($amqpQueue);

        $context->shouldReceive('topic')
            ->andReturn($amqpTopic);

        $amqpBind = new AmqpBind($amqpTopic, $amqpQueue, $this->event);
        $bind = \Mockery::mock(BindFactory::class, [$context])->makePartial();
        $bind->shouldReceive()->make($amqpQueue, $this->event)
            ->andReturn($amqpBind);

        $context->shouldReceive()->bind($amqpBind);

        $factory = new QueueFactory($context, $bind, 'test-app');

        $queue = $factory->make($this->event, null, true);

        self::assertInstanceOf(AmqpQueue::class, $queue);
        self::assertEquals(AmqpQueue::FLAG_DURABLE | AmqpQueue::FLAG_PASSIVE, $queue->getFlags());
        self::assertSame($amqpQueue, $queue);
        self::assertEquals($queueName, $queue->getQueueName());
    }

    public function testMakeWithCustomQueueName()
    {
        $customQueueName = 'customQueueName';

        $amqpQueue = new ImplAmqpQueue($customQueueName);
        $amqpTopic = \Mockery::spy(AmqpTopic::class);

        $context = \Mockery::mock(Context::class, [\Mockery::mock(AmqpContext::class), $amqpTopic])->makePartial();
        $context->shouldReceive()
            ->createQueue($customQueueName)
            ->andReturn($amqpQueue);

        $context->shouldReceive()->declareQueue($amqpQueue);

        $context->shouldReceive('topic')
            ->andReturn($amqpTopic);

        $amqpBind = new AmqpBind($amqpTopic, $amqpQueue, $this->event);
        $bind = \Mockery::mock(BindFactory::class, [$context])->makePartial();
        $bind->shouldReceive()->make($amqpQueue, $this->event)
            ->andReturn($amqpBind);

        $context->shouldReceive()->bind($amqpBind);

        $factory = new QueueFactory($context, $bind, 'test-app');

        $queue = $factory->make($this->event, $customQueueName, false);

        self::assertInstanceOf(AmqpQueue::class, $queue);
        self::assertEquals(AmqpQueue::FLAG_DURABLE, $queue->getFlags());
        self::assertSame($amqpQueue, $queue);
        self::assertEquals($customQueueName, $queue->getQueueName());
    }
}
