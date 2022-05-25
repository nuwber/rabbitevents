<?php

namespace RabbitEvents\Tests\Publisher;

use Carbon\Carbon;
use RabbitEvents\Publisher\MessageFactory;
use RabbitEvents\Publisher\Support\AbstractPublishableEvent;
use RabbitEvents\Foundation\Support\Payload;
use RabbitEvents\Foundation\Contracts\Transport;
use RabbitEvents\Foundation\Contracts\Payload as PayloadInterface;

class MessageFactoryTest extends TestCase
{
    private MessageFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new MessageFactory(\Mockery::mock(Transport::class));

        Carbon::setTestNow(Carbon::createFromTimeString('2022-03-21 00:00:00'));
    }

    public function testMakeFromArray(): void
    {
        $message = $this->factory->make(new Event());

        self::assertEquals('some.event', $message->event());
        self::assertInstanceOf(PayloadInterface::class, $message->payload());
        self::assertEquals(Carbon::now()->getTimestamp(), $message->getTimestamp());
    }

    public function testMakeFromPayloadObject(): void
    {
        $payload = new Payload(['pay' => 'load']);

        $event = new Event();

        $event->toPublish = $payload;

        $message = $this->factory->make($event);

        self::assertSame($payload, $message->payload());
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
