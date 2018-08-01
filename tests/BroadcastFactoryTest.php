<?php

namespace Nuwber\Events\Tests;

use Enqueue\AmqpLib\AmqpContext;
use Enqueue\AmqpLib\AmqpProducer;
use Interop\Amqp\Impl\AmqpMessage;
use Interop\Amqp\Impl\AmqpTopic;
use Interop\Amqp\Impl\AmqpQueue;
use Nuwber\Events\BroadcastFactory;

class BroadcastFactoryTest extends TestCase
{

    public function testSend()
    {
        $event = 'item.create';
        $message = new AmqpMessage('Hello!');
        $topic = new AmqpTopic('events');
        $queue = new AmqpQueue($event);


        $producer = \Mockery::mock(AmqpProducer::class);
        $producer->shouldReceive('send')
            ->with($topic, $message)
            ->once();

        $context = \Mockery::mock(AmqpContext::class)->makePartial();
        $context->shouldReceive('createProducer')
            ->andReturn($producer);

        $context->shouldReceive('declareQueue')
            ->once();

        $context->shouldReceive('createQueue')
            ->once()
            ->andReturn($queue);

        $context->shouldReceive('bind')->once();


        $factory = new BroadcastFactory($context, $topic);

        self::assertNull($factory->send($event, $message));
    }
}
