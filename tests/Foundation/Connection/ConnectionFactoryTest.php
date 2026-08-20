<?php

declare(strict_types=1);

namespace RabbitEvents\Tests\Foundation\Connection;

use InvalidArgumentException;
use Mockery as m;
use RabbitEvents\Foundation\Amqp\Connection as AmqpConnection;
use RabbitEvents\Foundation\Connection\ConnectionFactory;
use RabbitEvents\Foundation\Contracts\Connection;
use RabbitEvents\Tests\Foundation\TestCase;

class ConnectionFactoryTest extends TestCase
{
    private ConnectionFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new ConnectionFactory();
    }

    public function test_make_default_rabbitmq_driver(): void
    {
        $connection = $this->factory->make([
            'driver' => 'rabbitmq',
            'exchange' => 'test-exchange',
        ]);

        self::assertInstanceOf(AmqpConnection::class, $connection);
        self::assertInstanceOf(Connection::class, $connection);
    }

    public function test_make_amqp_driver_alias(): void
    {
        $connection = $this->factory->make([
            'driver' => 'amqp',
            'exchange' => 'test-exchange',
        ]);

        self::assertInstanceOf(AmqpConnection::class, $connection);
    }

    public function test_make_with_default_driver_when_unspecified(): void
    {
        $connection = $this->factory->make([
            'exchange' => 'test-exchange',
        ]);

        self::assertInstanceOf(AmqpConnection::class, $connection);
    }

    public function test_extend_with_custom_transport_driver(): void
    {
        $customConnection = m::mock(Connection::class);

        $this->factory->extend('custom-transport', function ($config, $container) use ($customConnection) {
            return $customConnection;
        });

        $connection = $this->factory->make(['driver' => 'custom-transport']);

        self::assertSame($customConnection, $connection);
    }

    public function test_throws_exception_on_unsupported_driver(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported transport connection driver [unknown-driver].');

        $this->factory->make(['driver' => 'unknown-driver']);
    }
}
