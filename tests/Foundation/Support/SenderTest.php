<?php

namespace RabbitEvents\Tests\Foundation\Support;

use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpMessage;
use Interop\Amqp\AmqpProducer;
use Mockery as m;
use RabbitEvents\Foundation\Message;
use RabbitEvents\Foundation\Support\Sender;
use RabbitEvents\Tests\Foundation\TestCase;

class SenderTest extends TestCase
{
    public function testSend(): void
    {
        $producer = m::mock(AmqpProducer::class);
        $topic = m::mock(AmqpTopic::class);

        $message = m::mock(Message::class);
        $message->shouldReceive()
            ->amqpMessage()
            ->andReturn(new AmqpMessage());

        $producer->shouldReceive()
            ->send($topic, $message->amqpMessage())
            ->once();

        $sender = new Sender($topic, $producer);

        $sender->send($message);
    }
}
