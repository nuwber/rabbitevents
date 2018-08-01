<?php

namespace Nuwber\Events\Tests;

use Enqueue\AmqpLib\AmqpConsumer;
use Enqueue\AmqpLib\AmqpContext;
use Interop\Amqp\Impl\AmqpQueue;
use Interop\Amqp\Impl\AmqpTopic;
use Nuwber\Events\ConsumerFactory;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ConsumerFactoryTest extends TestCase
{
    public function testMake()
    {
        $event = 'item.created';
        $serviceName = 'items';

        $queue = new AmqpQueue($event);
        $consumer = \Mockery::mock(AmqpConsumer::class)->makePartial();
        $context = \Mockery::mock(AmqpContext::class)->makePartial();

        $context->shouldReceive('createQueue')
            ->once()
            ->andReturn($queue);

        $context->shouldReceive('declareQueue')
            ->once();

        $context->shouldReceive('bind')->twice();

        $context->shouldReceive('createConsumer')
            ->once()
            ->andReturn($consumer);

        $consumerFactory = \Mockery::mock('overload:Nuwber\Events\ConsumerFactory');

        $consumerFactory->shouldReceive('bind')->with($event, $queue)->once();
        $consumerFactory->shouldReceive('bind')->with($serviceName . '-' . $event, $queue)->once();
        $consumerFactory->shouldReceive('make')->with($event, $serviceName)->once()->andReturn($consumer);

        $factory = new ConsumerFactory($context, new AmqpTopic('events'));

        self::assertEquals($consumer, $factory->make($event, $serviceName));
    }
}
