<?php

namespace Nuwber\Events\Tests\Queue;

use Interop\Amqp\AmqpContext;
use Interop\Queue\Producer;
use Interop\Queue\Topic;
use Mockery as m;
use Nuwber\Events\Queue\Context;
use Nuwber\Events\Queue\Message\Transport;
use Nuwber\Events\Tests\TestCase;

class ContextTest extends TestCase
{

    public function testContextCall()
    {
        $amqpContext = m::mock(AmqpContext::class);
        $amqpContext->shouldReceive()
            ->foo('bar')
            ->once()
            ->andReturn('result');

        $context = new Context($amqpContext, m::mock(Topic::class));

        self::assertEquals('result', $context->foo('bar'));
    }

    public function testTransport()
    {
        $amqpContext = m::mock(AmqpContext::class);
        $amqpContext->shouldReceive()
            ->createProducer()
            ->andReturn(m::mock(Producer::class));

        $context = new Context($amqpContext, m::mock(Topic::class));

        self::assertInstanceOf(Transport::class, $context->transport());

        $transport = m::mock(Transport::class);

        $context->setTransport($transport);

        self::assertSame($transport, $context->transport());
    }
}
