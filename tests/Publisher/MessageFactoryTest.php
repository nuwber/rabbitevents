<?php

namespace RabbitEvents\Tests\Publisher;

use RabbitEvents\Publisher\MessageFactory;
use RabbitEvents\Publisher\Support\AbstractPublishableEvent;
use RabbitEvents\Foundation\Support\Payload;
use RabbitEvents\Foundation\Contracts\Transport;
use RabbitEvents\Foundation\Message;
use RabbitEvents\Foundation\Contracts\Payload as PayloadInterface;

class MessageFactoryTest extends TestCase
{
    private MessageFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new MessageFactory(\Mockery::mock(Transport::class));
    }

    public function testMakeFromArray(): void
    {
        $message = $this->factory->make(new Event());

        self::assertInstanceOf(Message::class, $message);
        self::assertEquals('some.event', $message->event());
        self::assertInstanceOf(PayloadInterface::class, $message->payload());
    }

    public function testMakeFromPayloadObject(): void
    {
        $payload = new Payload(['pay' => 'load']);

        $event = new Event();

        $event->toPublish = $payload;

        $message = $this->factory->make($event);

        self::assertSame($payload, $message->payload());
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
