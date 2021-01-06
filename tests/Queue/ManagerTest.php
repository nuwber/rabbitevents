<?php

namespace Nuwber\Events\Tests\Queue;

use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\Impl\AmqpMessage;
use Mockery as m;
use Nuwber\Events\Queue\Manager;
use Nuwber\Events\Queue\Message\Transport;
use PHPUnit\Framework\TestCase;

class ManagerTest extends TestCase
{

    public function testAcknowledge()
    {
        $message = new AmqpMessage();

        $consumer = m::mock(AmqpConsumer::class);
        $consumer->shouldReceive()
            ->acknowledge($message);

        $manager = new FakeManager($consumer, m::mock(Transport::class));
        $manager->acknowledge($message);

        self::assertTrue($manager->acknowledged);
    }

    public function testSend()
    {
        $message = new AmqpMessage();

        $transport = new FakeTransport();

        $manager = new Manager(m::mock(AmqpConsumer::class), $transport);
        $manager->send($message, 10);

        self::assertSame($message, $transport->message);
        self::assertEquals(10, $transport->delay);
    }

    public function testGetQueueName()
    {
        $queue = m::mock(AmqpQueue::class);
        $queue->shouldReceive()
            ->getQueueName()
            ->andReturn('my-queue');
        $consumer = m::mock(AmqpConsumer::class);
        $consumer->shouldReceive()
            ->getQueue()
            ->andReturn($queue);

        $manager = new Manager($consumer, new FakeTransport());
        self::assertEquals('my-queue', $manager->getEvent());
    }

    public function testNextMessage()
    {
        $message = new AmqpMessage();
        $consumer = m::mock(AmqpConsumer::class);
        $consumer->shouldReceive()
            ->receive(10)
            ->andReturn($message);

        $manager = new Manager($consumer, new FakeTransport());
        self::assertEquals($message, $manager->nextMessage(10));
    }

    public function testRelease()
    {
        $message = new AmqpMessage();
        $transport = new FakeTransport();

        $manager = new FakeManager(m::mock(AmqpConsumer::class), $transport);

        $manager->release($message, 1, 10);

        self::assertTrue($manager->sent);
        self::assertTrue($manager->released);
        self::assertInstanceOf(AmqpMessage::class, $transport->message);
        self::assertNotEquals($message, $transport->message);
        self::assertEquals(2, $transport->message->getProperty('x-attempts'));
    }
}

class FakeManager extends Manager
{
    public $sent = false;
    public $released = false;
    public $acknowledged = false;

    public function send(\Interop\Amqp\AmqpMessage $message, int $delay = 0): void
    {
        parent::send($message, $delay);
        $this->sent = true;
    }

    public function release(\Interop\Amqp\AmqpMessage $message, int $attempts = 1, int $delay = 0): void
    {
        parent::release($message, $attempts, $delay);
        $this->released = true;
    }

    public function acknowledge(\Interop\Amqp\AmqpMessage $amqpMessage): void
    {
        parent::acknowledge($amqpMessage);
        $this->acknowledged = true;
    }
}

class FakeTransport implements Transport
{
    /**
     * @var Message
     */
    public $message;
    /**
     * @var int
     */
    public $delay;

    public function send(\Interop\Queue\Message $message, int $delay = 0): void
    {
        $this->message = $message;
        $this->delay = $delay;
    }
}
