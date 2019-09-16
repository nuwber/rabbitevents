<?php

namespace Nuwber\Events\Tests;

use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpProducer;
use Interop\Amqp\AmqpMessage;
use Interop\Amqp\Impl\AmqpTopic;
use Nuwber\Events\Publisher;

class PublisherTest extends TestCase
{
    private $event = 'item.changed';

    private $payload = ['data' => 'payload'];

    public function testSend()
    {
        $topic = new AmqpTopic('events');

        $message = \Mockery::mock(AmqpMessage::class)->makePartial();
        $message->shouldReceive('setRoutingKey')
            ->with($this->event)
            ->once();

        $producer = \Mockery::mock(AmqpProducer::class);
        $producer->shouldReceive('send')
            ->with($topic, $message)
            ->once();

        $context = \Mockery::mock(AmqpContext::class)->makePartial();
        $context->shouldReceive('createProducer')
            ->andReturn($producer);
        $context->shouldReceive('createMessage')
            ->with(json_encode($this->payload, JSON_UNESCAPED_UNICODE))
            ->andReturn($message);

        $publisher = new Publisher($context, $topic);

        self::assertNull($publisher->send($this->event, $this->payload));
    }
}
