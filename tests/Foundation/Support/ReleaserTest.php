<?php

namespace RabbitEvents\Tests\Foundation\Support;

use Interop\Amqp\AmqpProducer;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\Impl\AmqpMessage;
use Interop\Queue\Exception\DeliveryDelayNotSupportedException;
use RabbitEvents\Foundation\Message;
use RabbitEvents\Foundation\Support\Releaser;
use RabbitEvents\Tests\Foundation\TestCase;
use Mockery as m;
use RabbitEvents\Tests\Listener\Payload;

class ReleaserTest extends TestCase
{
    private $producer;
    private $message;
    private $queue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queue = m::mock(AmqpQueue::class);
        $this->message = new Message('some.event', new Payload([]));
        $this->message->setAmqpMessage($amqpMessage = new AmqpMessage());

        $this->producer = m::mock(AmqpProducer::class);
        $this->producer->shouldReceive()
            ->send($this->queue, $amqpMessage)
            ->once();
    }

    public function testDeliveryDelay()
    {
        $this->producer->shouldReceive()
            ->setDeliveryDelay(1000);

        (new Releaser($this->queue, $this->producer))
            ->send($this->message);
    }

    public function testNotThrowExceptionIfDelayNotSupported(): void
    {
        $this->producer->shouldReceive()
            ->setDeliveryDelay(1000)
            ->andThrow(DeliveryDelayNotSupportedException::class);

        (new Releaser($this->queue, $this->producer))
            ->send($this->message);
    }
}
