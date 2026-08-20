<?php

declare(strict_types=1);

namespace RabbitEvents\Tests\Foundation\Connection;

use Illuminate\Container\Container;
use InvalidArgumentException;
use Mockery as m;
use RabbitEvents\Foundation\Connection\ConnectionFactory;
use RabbitEvents\Foundation\Connection\ConnectionManager;
use RabbitEvents\Foundation\Context;
use RabbitEvents\Foundation\Contracts\Connection;
use RabbitEvents\Foundation\Contracts\Destination;
use RabbitEvents\Foundation\Contracts\Producer;
use RabbitEvents\Foundation\Contracts\QueueConsumer;
use RabbitEvents\Foundation\Serialization\SerializerRegistry;
use RabbitEvents\Tests\Foundation\TestCase;

class ConnectionManagerTest extends TestCase
{
    private Container $app;
    private ConnectionFactory $factory;
    private ConnectionManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = new Container();
        $this->factory = m::mock(ConnectionFactory::class);
        $this->manager = new ConnectionManager($this->app, $this->factory);
    }

    public function test_resolves_default_connection(): void
    {
        $this->app['config'] = [
            'rabbitevents' => [
                'default' => 'rabbitmq',
                'connections' => [
                    'rabbitmq' => ['driver' => 'rabbitmq', 'exchange' => 'events'],
                ],
            ],
        ];

        $mockConnection = m::mock(Connection::class);
        $this->factory->shouldReceive('make')
            ->once()
            ->with(['driver' => 'rabbitmq', 'exchange' => 'events'])
            ->andReturn($mockConnection);

        $connection = $this->manager->connection();

        self::assertSame($mockConnection, $connection);
    }

    public function test_caches_resolved_connections(): void
    {
        $this->app['config'] = [
            'rabbitevents' => [
                'default' => 'rabbitmq',
                'connections' => [
                    'rabbitmq' => ['driver' => 'rabbitmq', 'exchange' => 'events'],
                ],
            ],
        ];

        $mockConnection = m::mock(Connection::class);
        $this->factory->shouldReceive('make')
            ->once()
            ->with(['driver' => 'rabbitmq', 'exchange' => 'events'])
            ->andReturn($mockConnection);

        $first = $this->manager->connection();
        $second = $this->manager->connection();

        self::assertSame($first, $second);
    }

    public function test_resolves_named_connection(): void
    {
        $this->app['config'] = [
            'rabbitevents' => [
                'default' => 'rabbitmq',
                'connections' => [
                    'rabbitmq' => ['driver' => 'rabbitmq'],
                    'custom' => ['driver' => 'custom', 'host' => 'localhost'],
                ],
            ],
        ];

        $mockCustomConnection = m::mock(Connection::class);
        $this->factory->shouldReceive('make')
            ->once()
            ->with(['driver' => 'custom', 'host' => 'localhost'])
            ->andReturn($mockCustomConnection);

        $connection = $this->manager->connection('custom');

        self::assertSame($mockCustomConnection, $connection);
    }

    public function test_throws_exception_when_connection_not_configured(): void
    {
        $this->app['config'] = [
            'rabbitevents' => [
                'default' => 'rabbitmq',
                'connections' => [],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('RabbitEvents connection [nonexistent] is not defined.');

        $this->manager->connection('nonexistent');
    }

    public function test_purge_clears_cached_connection(): void
    {
        $this->app['config'] = [
            'rabbitevents' => [
                'default' => 'rabbitmq',
                'connections' => [
                    'rabbitmq' => ['driver' => 'rabbitmq'],
                ],
            ],
        ];

        $mockConnection1 = m::mock(Connection::class);
        $mockConnection2 = m::mock(Connection::class);

        $this->factory->shouldReceive('make')
            ->twice()
            ->andReturn($mockConnection1, $mockConnection2);

        $first = $this->manager->connection();
        $this->manager->purge('rabbitmq');
        $second = $this->manager->connection();

        self::assertNotSame($first, $second);
    }

    public function test_set_default_connection(): void
    {
        $this->app['config'] = [
            'rabbitevents' => [
                'default' => 'rabbitmq',
            ],
        ];

        $this->manager->setDefaultConnection('secondary');
        self::assertEquals('secondary', $this->manager->getDefaultConnection());
    }

    public function test_extend_delegates_to_factory(): void
    {
        $callback = function () {};
        $this->factory->shouldReceive('extend')
            ->once()
            ->with('redis', $callback);

        $result = $this->manager->extend('redis', $callback);
        self::assertSame($this->manager, $result);
    }

    public function test_context_creates_context_instance(): void
    {
        $this->app['config'] = [
            'rabbitevents' => [
                'default' => 'rabbitmq',
                'connections' => [
                    'rabbitmq' => ['driver' => 'rabbitmq'],
                ],
            ],
        ];

        $mockConnection = m::mock(Connection::class);
        $this->factory->shouldReceive('make')->andReturn($mockConnection);

        $mockRegistry = m::mock(SerializerRegistry::class);
        $this->app->instance(SerializerRegistry::class, $mockRegistry);

        $context = $this->manager->context();

        self::assertInstanceOf(Context::class, $context);
        self::assertSame($mockConnection, $context->connection);
    }

    public function test_proxies_connection_methods_to_default_connection(): void
    {
        $this->app['config'] = [
            'rabbitevents' => [
                'default' => 'rabbitmq',
                'connections' => [
                    'rabbitmq' => ['driver' => 'rabbitmq'],
                ],
            ],
        ];

        $mockProducer = m::mock(Producer::class);
        $mockConsumer = m::mock(QueueConsumer::class);
        $mockTopic = m::mock(Destination::class);
        $mockQueue = m::mock(Destination::class);

        $mockConnection = m::mock(Connection::class);
        $mockConnection->shouldReceive('createProducer')->once()->andReturn($mockProducer);
        $mockConnection->shouldReceive('makeConsumer')->once()->with($mockQueue)->andReturn($mockConsumer);
        $mockConnection->shouldReceive('makeTopic')->once()->andReturn($mockTopic);
        $mockConnection->shouldReceive('makeQueue')->once()->with('q', ['e'], $mockTopic)->andReturn($mockQueue);

        $this->factory->shouldReceive('make')->andReturn($mockConnection);

        self::assertSame($mockProducer, $this->manager->createProducer());
        self::assertSame($mockConsumer, $this->manager->makeConsumer($mockQueue));
        self::assertSame($mockTopic, $this->manager->makeTopic());
        self::assertSame($mockQueue, $this->manager->makeQueue('q', ['e'], $mockTopic));
    }
}
