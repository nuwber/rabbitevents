<?php

declare(strict_types=1);

namespace RabbitEvents\Tests;

use Mockery as m;
use RabbitEvents\Foundation\Consumer;
use RabbitEvents\Foundation\Contracts\Serializer;
use RabbitEvents\Foundation\Contracts\ContentType;
use RabbitEvents\Foundation\Message;
use RabbitEvents\Foundation\Serialization\SerializerRegistry;
use RabbitEvents\Listener\ListenerOptions;

/**
 * Trait for creating common test mocks and stubs.
 * 
 * This trait provides reusable helper methods for creating frequently-used
 * mocks across the test suite, reducing code duplication and making tests
 * more maintainable.
 */
trait MocksCommonObjects
{
    /**
     * Create a mock Application instance.
     *
     * @param array $config Configuration for the mock
     * @return \Mockery\MockInterface
     */
    protected function mockApplication(array $config = []): \Mockery\MockInterface
    {
        $defaults = [
            'path.bootstrap' => false,
            'path.listeners' => __DIR__ . '/Listener/Fixtures/Listeners',
            'path.base' => __DIR__ . '/Listener/Fixtures',
            'base_path' => __DIR__ . '/Listener/Fixtures',
            'namespace' => 'RabbitEvents\\Tests\\Listener\\Fixtures\\\\',
        ];

        $config = array_merge($defaults, $config);

        $app = m::mock('Illuminate\\Foundation\\Application');
        $app->shouldReceive('bound')->with('path.bootstrap')->andReturn($config['path.bootstrap']);
        $app->shouldReceive('path')->with('Listeners')->andReturn($config['path.listeners']);
        $app->shouldReceive('path')->withNoArgs()->andReturn($config['path.base']);
        $app->shouldReceive('basePath')->andReturn($config['base_path']);
        $app->shouldReceive('getNamespace')->andReturn($config['namespace']);

        return $app;
    }

    /**
     * Create a mock Consumer instance.
     *
     * @param array $methods Method stubs to configure
     * @return \Mockery\MockInterface
     */
    protected function mockConsumer(array $methods = []): \Mockery\MockInterface
    {
        $consumer = m::mock(Consumer::class);

        foreach ($methods as $method => $return) {
            $consumer->shouldReceive($method)->andReturn($return);
        }

        return $consumer;
    }

    /**
     * Create a mock SerializerRegistry instance.
     *
     * @param Serializer|null $defaultSerializer Optional default serializer
     * @param array $serializers Map of content-type => serializer
     * @return \Mockery\MockInterface
     */
    protected function mockSerializerRegistry(
        ?Serializer $defaultSerializer = null,
        array $serializers = []
    ): \Mockery\MockInterface {
        $registry = m::mock(SerializerRegistry::class);

        if ($defaultSerializer !== null) {
            $registry->shouldReceive('getDefault')->andReturn($defaultSerializer);
        }

        foreach ($serializers as $contentType => $serializer) {
            $registry->shouldReceive('get')->with($contentType)->andReturn($serializer);
        }

        return $registry;
    }

    /**
     * Create a mock Serializer instance.
     *
     * @param string $contentType The content type this serializer handles
     * @param array $methods Additional methods to configure
     * @return \Mockery\MockInterface
     */
    protected function mockSerializer(string $contentType, array $methods = []): \Mockery\MockInterface
    {
        $type = m::mock(ContentType::class);
        $type->shouldReceive('__toString')->andReturn($contentType);
        $type->shouldReceive('getValue')->andReturn($contentType);

        $serializer = m::mock(Serializer::class);
        $serializer->shouldReceive('contentType')->andReturn($type);

        foreach ($methods as $method => $return) {
            $serializer->shouldReceive($method)->andReturn($return);
        }

        return $serializer;
    }

    /**
     * Create a mock Message instance.
     *
     * @param string $event Event name
     * @param mixed $payload Event payload
     * @param int $attempts Number of attempts
     * @return \Mockery\MockInterface
     */
    protected function mockMessage(
        string $event = 'test.event',
        mixed $payload = null,
        int $attempts = 1
    ): \Mockery\MockInterface {
        $message = m::mock(Message::class);
        $message->shouldReceive('attempts')->andReturn($attempts);

        return $message;
    }

    /**
     * Create a ListenerOptions instance with defaults.
     *
     * @param array $overrides Override default values
     * @return ListenerOptions
     */
    protected function createListenerOptions(array $overrides = []): ListenerOptions
    {
        $defaults = [
            'service' => 'test-app',
            'connectionName' => 'rabbitmq',
            'events' => ['rabbit.event'],
            'memory' => 128,
            'maxTries' => 0,
            'timeout' => 60,
            'sleep' => 5,
        ];

        $config = array_merge($defaults, $overrides);

        return new ListenerOptions(
            service: $config['service'],
            connectionName: $config['connectionName'],
            events: $config['events'],
            memory: $config['memory'],
            maxTries: $config['maxTries'],
            timeout: $config['timeout'],
            sleep: $config['sleep'],
        );
    }
}
