<?php

namespace RabbitEvents\Tests\Foundation\Support;

use RabbitEvents\Foundation\Message;
use RabbitEvents\Foundation\Support\Sender;
use RabbitEvents\Tests\Foundation\TestCase;
use RabbitEvents\Tests\Foundation\Stubs\DestinationStub;
use RabbitEvents\Tests\Foundation\Stubs\ProducerStub;
use RabbitEvents\Tests\Foundation\Stubs\TransportMessageStub;
use Mockery as m;

class SenderTest extends TestCase
{
    public function testSend(): void
    {
        $producerStub = new ProducerStub();
        $topicStub = new DestinationStub();

        $transportMessageStub = new TransportMessageStub();

        $message = m::mock(Message::class);
        $message->shouldReceive('transportMessage')
            ->andReturn($transportMessageStub);

        $sender = new Sender($topicStub, $producerStub);

        $sender->send($message);
        
        self::assertCount(1, $producerStub->sent);
        self::assertSame($topicStub, $producerStub->sent[0]['destination']);
        self::assertSame($transportMessageStub, $producerStub->sent[0]['message']);
    }
}
