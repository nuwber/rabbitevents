<?php

namespace RabbitEvents\Tests\Foundation\Amqp;

use Interop\Amqp\AmqpDestination;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpTopic as ImplAmqpTopic;
use Mockery as m;
use RabbitEvents\Foundation\Amqp\TopicDestinationFactory;
use RabbitEvents\Foundation\Connection;
use RabbitEvents\Foundation\Context;
use RabbitEvents\Tests\Foundation\TestCase;

class DestinationFactoryTest extends TestCase
{

    public function testMake()
    {
        $exchange = 'events';
        $connection = m::mock(Connection::class);
        $connection->shouldReceive()
            ->getConfig('exchange')
            ->andReturn($exchange);

        $context = m::mock(Context::class);
        $context->shouldReceive()
            ->connection()
            ->andReturn($connection);
        $context->shouldReceive()
            ->createTopic($exchange)
            ->andReturn($amqpTopic = new ImplAmqpTopic($exchange));
        $context->shouldReceive()
            ->declareTopic($amqpTopic);

        $factory = new TopicDestinationFactory($context);
        $topic = $factory->make();

        self::assertSame($amqpTopic, $topic);
        self::assertEquals(AmqpTopic::TYPE_TOPIC, $topic->getType());
        self::assertEquals(AmqpDestination::FLAG_DURABLE, $topic->getFlags());
    }
}
