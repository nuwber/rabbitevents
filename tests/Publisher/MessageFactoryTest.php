<?php

namespace RabbitEvents\Tests\Publisher;

use Carbon\Carbon;
use RabbitEvents\Publisher\MessageFactory;
use RabbitEvents\Publisher\Support\AbstractPublishableEvent;
use RabbitEvents\Foundation\Support\JsonPayload;
use RabbitEvents\Foundation\Contracts\Transport;
use RabbitEvents\Foundation\Contracts\Payload;

class MessageFactoryTest extends TestCase
{
    private MessageFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $registry = \Mockery::mock(\RabbitEvents\Foundation\Serialization\SerializerRegistry::class);
        $registry->shouldReceive('get')->andReturn(\Mockery::mock(\RabbitEvents\Foundation\Contracts\Serializer::class));

        $this->factory = new MessageFactory($registry);

        Carbon::setTestNow(Carbon::createFromTimeString('2022-03-21 00:00:00'));
    }

    public function testMakeFromArray(): void
    {
        $payload = new JsonPayload([]);

        $serializer = \Mockery::mock(\RabbitEvents\Foundation\Contracts\Serializer::class);
        $serializer->shouldReceive('serialize')->andReturn($payload);

        $registry = \Mockery::mock(\RabbitEvents\Foundation\Serialization\SerializerRegistry::class);
        $registry->shouldReceive('get')->with('application/json')->andReturn($serializer);
        
        $this->factory = new MessageFactory($registry);

        $message = $this->factory->make(new Event());

        self::assertEquals('some.event', $message->event);
        self::assertInstanceOf(Payload::class, $message->payload);
        self::assertEquals(Carbon::now()->getTimestamp(), $message->getTimestamp());
    }

    public function testMakeFromPayloadObject(): void
    {
        $payload = new JsonPayload(['pay' => 'load']);

        $event = new Event();
        $event->toPublish = $payload;

        $serializer = \Mockery::mock(\RabbitEvents\Foundation\Contracts\Serializer::class);
        $serializer->shouldReceive('serialize')->with($payload)->andReturn($payload);

        $registry = \Mockery::mock(\RabbitEvents\Foundation\Serialization\SerializerRegistry::class);
        $registry->shouldReceive('get')->with('application/json')->andReturn($serializer);

        $factory = new MessageFactory($registry);

        $message = $factory->make($event);

        self::assertSame($payload, $message->payload);
        self::assertEquals(Carbon::now()->getTimestamp(), $message->getTimestamp());
    }
}

class Event extends AbstractPublishableEvent
{
    public $toPublish = ['pay' => 'load'];

    public function publishEventKey(): string
    {
        return 'some.event';
    }

    public function toPublish(): mixed
    {
        return $this->toPublish;
    }
}
