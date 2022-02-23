<?php

namespace RabbitEvents\Tests\Foundation\Support;

use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpMessage;
use Interop\Amqp\AmqpProducer;
use Interop\Queue\Exception\DeliveryDelayNotSupportedException;
use Mockery as m;
use RabbitEvents\Foundation\Context;
use RabbitEvents\Foundation\Message;
use RabbitEvents\Foundation\Support\Sender;
use RabbitEvents\Tests\Foundation\TestCase;

class SenderTest extends TestCase
{
    /**
     * @var AmqpTopic
     */
    private $topic;
    /**
     * @var AmqpMessage
     */
    private $message;
    private $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->topic = m::mock(AmqpTopic::class);
        $this->message = m::mock(Message::class);
        $this->message->shouldReceive()
            ->amqpMessage()
            ->andReturn(new AmqpMessage());

        $this->context = m::mock(Context::class);
        $this->producer = m::mock(AmqpProducer::class);

        $this->producer->shouldReceive()
            ->send($this->topic, $this->message->amqpMessage())
            ->once();

        $this->context->shouldReceive('destination')
            ->andReturn($this->topic);
        $this->context->shouldReceive()
            ->createProducer()
            ->andReturn($this->producer);
    }

    public function testSend(): void
    {
        $this->producer->shouldReceive()
            ->setDeliveryDelay(1000);

        $sender = new Sender($this->context);

        $sender->send($this->message, 1);
    }

    public function testNotThrowExceptionIfDelayNotSupported(): void
    {
        $this->producer->shouldReceive()
            ->setDeliveryDelay(1000)
            ->andThrow(DeliveryDelayNotSupportedException::class);

        $sender = new Sender($this->context);
        $sender->send($this->message, 1);
    }
}
