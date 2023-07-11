<?php

namespace RabbitEvents\Tests\Foundation\Amqp;

use Interop\Amqp\AmqpDestination;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpTopic as ImplAmqpTopic;
use Mockery as m;
use RabbitEvents\Foundation\Amqp\DestinationTopicFactory;
use RabbitEvents\Foundation\Context;
use RabbitEvents\Tests\Foundation\TestCase;

class DestinationTopicFactoryTest extends TestCase
{

    public function testMakeAndDeclare()
    {
        $exchange = 'events';

        $context = m::mock(Context::class);
        $context->shouldReceive()
            ->createTopic($exchange)
            ->andReturn($amqpTopic = new ImplAmqpTopic($exchange));
        $context->shouldReceive()
            ->declareTopic($amqpTopic);

        $factory = new DestinationTopicFactory($context);
        $topic = $factory->makeAndDeclare($exchange);

        self::assertSame($amqpTopic, $topic);
        self::assertEquals(AmqpTopic::TYPE_TOPIC, $topic->getType());
        self::assertEquals(AmqpDestination::FLAG_DURABLE, $topic->getFlags());
    }
}
