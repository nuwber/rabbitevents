<?php

namespace RabbitEvents\Tests\Foundation\Amqp;

use Interop\Amqp\AmqpBind;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use RabbitEvents\Foundation\Amqp\BindFactory;
use RabbitEvents\Foundation\Context;
use RabbitEvents\Tests\Foundation\TestCase;

class BindFactoryTest extends TestCase
{
    public function testMake()
    {
        $topic  = \Mockery::mock(AmqpTopic::class);

        $queue = \Mockery::mock(AmqpQueue::class);

        $factory = new BindFactory();
        $bind = $factory->make($topic, $queue, 'item.created');

        self::assertInstanceOf(AmqpBind::class, $bind);

        self::assertEquals($topic, $bind->getTarget());
        self::assertEquals($queue, $bind->getSource());
        self::assertEquals('item.created', $bind->getRoutingKey());
    }
}
