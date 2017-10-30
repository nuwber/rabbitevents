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
        $queue = new AmqpQueue('');

        $consumer = \Mockery::mock(AmqpConsumer::class)->makePartial();

        $context = \Mockery::mock(AmqpContext::class)->makePartial();
        $context->shouldReceive('createConsumer')
            ->once()
            ->andReturn($consumer);

        $context->shouldReceive('createTemporaryQueue')
            ->once()
            ->andReturn($queue);

        $events = ['item.created', 'item.updated'];
        $context->shouldReceive('bind')->twice();

        $factory = new ConsumerFactory($context, new AmqpTopic('events'));

        self::assertEquals($consumer, $factory->make($events));
    }
}
