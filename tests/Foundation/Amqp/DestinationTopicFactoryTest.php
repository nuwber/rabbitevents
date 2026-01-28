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

        $amqpContext = m::mock(\Interop\Amqp\AmqpContext::class);
        $amqpContext->shouldReceive()
            ->createTopic($exchange)
            ->andReturn($amqpTopic = new ImplAmqpTopic($exchange));
        $amqpContext->shouldReceive()
            ->declareTopic($amqpTopic);

        $connection = m::mock(\RabbitEvents\Foundation\Amqp\Connection::class);
        $connection->shouldReceive('getConfig')
            ->with('durable', true)
            ->andReturn(true);

        $factory = new DestinationTopicFactory($amqpContext, $connection);
        $topic = $factory->makeAndDeclare($exchange);

        self::assertSame($amqpTopic, $topic);
        self::assertEquals(AmqpTopic::TYPE_TOPIC, $topic->getType());
        self::assertEquals(AmqpDestination::FLAG_DURABLE, $topic->getFlags());
    }
}
