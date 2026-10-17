<?php

namespace RabbitEvents\Tests\Foundation\Amqp;

use Enqueue\AmqpTools\RabbitMqDlxDelayStrategy;
use Enqueue\AmqpTools\DelayStrategy;
use Interop\Amqp\AmqpConnectionFactory;
use Interop\Amqp\AmqpContext;
use RabbitEvents\Foundation\Amqp\Connection;
use RabbitEvents\Tests\Foundation\TestCase;
use Mockery as m;

class ConnectionTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = new Connection(['exchange' => 'events', 'delay_strategy' => RabbitMqDlxDelayStrategy::class]);
    }

    public function testConnect(): void
    {
        self::assertInstanceOf(AmqpConnectionFactory::class, $this->connection->connect());
    }

    public function testDelayStrategySetter(): void
    {
        $strategy = \Mockery::mock(DelayStrategy::class);
        $this->connection->setDelayStrategy($strategy);

        self::assertSame($strategy, $this->connection->getDelayStrategy());
    }

    public function testGetDelayStrategy(): void
    {
        self::assertInstanceOf(DelayStrategy::class, $this->connection->getDelayStrategy());
    }

    public function testDeduplicationStrategySetter(): void
    {
        $strategy = \Mockery::mock(\RabbitEvents\Foundation\Contracts\Deduplicator::class);
        $this->connection->setDeduplicationStrategy($strategy);

        self::assertSame($strategy, $this->connection->getDeduplicationStrategy());
    }

    public function testGetDeduplicationStrategy(): void
    {
        self::assertInstanceOf(
            \RabbitEvents\Foundation\Contracts\Deduplicator::class,
            $this->connection->getDeduplicationStrategy()
        );
    }

    public function testGetConfig()
    {
        self::assertEquals('events', $this->connection->getConfig('exchange'));
    }
}
