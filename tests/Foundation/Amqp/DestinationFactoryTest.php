<?php

namespace RabbitEvents\Tests\Foundation\Amqp;

use Interop\Amqp\AmqpDestination;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpTopic as ImplAmqpTopic;
use RabbitEvents\Foundation\Amqp\DestinationFactory;
use RabbitEvents\Foundation\Context;
use RabbitEvents\Tests\Foundation\TestCase;

class DestinationFactoryTest extends TestCase
{

    public function testMake()
    {
        $exchange = 'events';

        $context = \Mockery::mock(Context::class);
        $context->shouldReceive('createTopic')
            ->andReturn($amqpTopic = new ImplAmqpTopic($exchange));
        $context->shouldReceive()
            ->declareTopic($amqpTopic);

        $factory = new DestinationFactory($context);
        $topic = $factory->make($exchange);

        self::assertSame($amqpTopic, $topic);
        self::assertEquals(AmqpTopic::TYPE_TOPIC, $topic->getType());
        self::assertEquals(AmqpDestination::FLAG_DURABLE, $topic->getFlags());
    }
}
