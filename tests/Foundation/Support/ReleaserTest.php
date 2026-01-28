<?php

namespace RabbitEvents\Tests\Foundation\Support;

use Interop\Queue\Exception\DeliveryDelayNotSupportedException;
use RabbitEvents\Foundation\Message;
use RabbitEvents\Foundation\Support\Releaser;
use RabbitEvents\Tests\Foundation\TestCase;
use RabbitEvents\Tests\Foundation\Stubs\DestinationStub;
use RabbitEvents\Tests\Foundation\Stubs\ProducerStub;
use RabbitEvents\Tests\Foundation\Stubs\TransportMessageStub;
use RabbitEvents\Tests\Listener\Payload;

class ReleaserTest extends TestCase
{
    private $producerStub;
    private $message;
    private $queueStub;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queueStub = new DestinationStub();
        $this->message = new Message('some.event', new Payload([]));
        $transportMessage = new TransportMessageStub();
        $this->message->setTransportMessage($transportMessage);

        $this->producerStub = new ProducerStub();
    }

    public function testDeliveryDelay()
    {
        $releaser = new Releaser($this->queueStub, $this->producerStub);
        
        $releaser->setDelay(1);
        $releaser->send($this->message);
            
        self::assertEquals(1000, $this->producerStub->deliveryDelay);
        self::assertCount(1, $this->producerStub->sent);
    }

    public function testNotThrowExceptionIfDelayNotSupported(): void
    {
        // Simulate exception on setDeliveryDelay
        $this->producerStub->throwException(new DeliveryDelayNotSupportedException(), 'setDeliveryDelay');

        $releaser = new Releaser($this->queueStub, $this->producerStub);
        
        $releaser->setDelay(1);
        $releaser->send($this->message);
            
        // Should send anyway, despite exception on delay
        self::assertCount(1, $this->producerStub->sent);
    }
}
