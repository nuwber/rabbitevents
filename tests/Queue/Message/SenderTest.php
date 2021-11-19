<?php

namespace Nuwber\Events\Tests\Queue\Message;

use Interop\Amqp\Impl\AmqpMessage;
use Interop\Queue\Destination;
use Interop\Queue\Exception\DeliveryDelayNotSupportedException;
use Interop\Queue\Producer;
use Mockery as m;
use Nuwber\Events\Queue\Message\Sender;
use Nuwber\Events\Tests\TestCase;

class SenderTest extends TestCase
{
    /**
     * @var Destination
     */
    private $topic;
    /**
     * @var AmqpMessage
     */
    private $message;

    protected function setUp(): void
    {
        parent::setUp();

        $this->topic = m::mock(Destination::class);
        $this->message = new AmqpMessage();
    }

    public function testSend()
    {
        $producer = m::mock(Producer::class);
        $producer->shouldReceive()
            ->setDeliveryDelay(1000);

        $producer->shouldReceive()
            ->send($this->topic, $this->message);

        $sender = new Sender($producer, $this->topic);
        self::assertNull($sender->send($this->message, 1));
    }

    public function testNotThrowExceptionIfDelayNotSupported()
    {
        $producer = m::mock(Producer::class);
        $producer->shouldReceive()
            ->setDeliveryDelay(1000)
            ->andThrow(DeliveryDelayNotSupportedException::class);

        $producer->shouldReceive()
            ->send($this->topic, $this->message);

        $sender = new Sender($producer, $this->topic);
        self::assertNull($sender->send($this->message, 1));
    }
}
