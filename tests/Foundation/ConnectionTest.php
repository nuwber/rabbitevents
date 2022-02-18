<?php

namespace RabbitEvents\Tests\Foundation;

use Enqueue\AmqpTools\DelayStrategy;
use Enqueue\AmqpTools\RabbitMqDlxDelayStrategy;
use Interop\Amqp\AmqpConnectionFactory;
use RabbitEvents\Foundation\Connection;

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
}
