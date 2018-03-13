<?php

namespace Nuwber\Events\Tests;

use Enqueue\AmqpLib\AmqpConsumer;
use Enqueue\AmqpLib\AmqpContext;
use Interop\Amqp\Impl\AmqpQueue;
use Interop\Amqp\Impl\AmqpTopic;
use Nuwber\Events\ConsumerFactory;

class ConsumerFactoryTest extends TestCase
{

    public function testMake()
    {
        $event = 'item.created';
        $queue = new AmqpQueue($event);

        $consumer = \Mockery::mock(AmqpConsumer::class)->makePartial();

        $context = \Mockery::mock(AmqpContext::class)->makePartial();
        $context->shouldReceive('createConsumer')
            ->once()
            ->andReturn($consumer);

        $context->shouldReceive('declareQueue')
            ->once();

        $context->shouldReceive('createQueue')
            ->once()
            ->andReturn($queue);

        $context->shouldReceive('bind')->once();

        $factory = new ConsumerFactory($context, new AmqpTopic('events'));

        self::assertEquals($consumer, $factory->make($event));
    }
}
