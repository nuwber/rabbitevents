<?php

namespace RabbitEvents\Tests\Listener\Message;

use Illuminate\Container\Container;
use Mockery as m;
use RabbitEvents\Foundation\Contracts\Transport;
use RabbitEvents\Foundation\Message;
use RabbitEvents\Listener\Message\Handler;
use RabbitEvents\Tests\Listener\Payload;
use RabbitEvents\Tests\Listener\TestCase;

class HandlerTest extends TestCase
{
    private $handler;
    private $event = 'event.called';
    private $listenerClass = 'ListenerClass';

    public function setUp(): void
    {
        $callback = function ($event, $payload) {
            return "Publisher: $event. Item id: {$payload['id']}";
        };

        $this->handler = $this->getHandler($callback);
    }

    public function testHandle(): void
    {
        self::assertEquals("Publisher: $this->event. Item id: 1", $this->handler->handle());
    }

    public function testGetName(): void
    {
        $handler = new Handler(
            m::mock(Container::class),
            $this->getMessage(),
            static fn($event, $payload) => $event,
            $this->listenerClass,
            m::mock(Transport::class)
        );

        self::assertEquals("{$this->event}:{$this->listenerClass}", $handler->getName());
    }

    public function testExceptionFired(): void
    {
        $class = new class() {
            public function fire()
            {
                throw new \Exception("Exception in the `fire` method");
            }
        };

        $callback = function () use ($class) {
            return call_user_func_array([new $class, 'fire'], []);
        };

        $handler = $this->getHandler($callback);

        $this->expectExceptionMessage("Exception in the `fire` method");

        $handler->handle();
    }

    public function testFail(): void
    {
        $exception = new \Exception("Exception in the `fire` method");

        $this->expectExceptionMessage($exception->getMessage());

        $container = new Container();
        $listener = $container->instance(FailingListener::class, new FailingListener());

        $message = $this->getMessage();
        $transport = m::spy(Transport::class);

        $handler = new Handler($container, $message, static fn($event, $payload) => $event, FailingListener::class, $transport);

        $handler->fail($exception);

        self::assertTrue($handler->hasFailed());
        self::assertEquals($message->payload(), $listener->payload);

        $transport->shouldHaveReceived('send', m::type(Message::class));
    }

    public function testFailClosure(): void
    {
        $exception = new \Exception("Exception in `fire` method");

        $handler = $this->getHandler(static fn($event, $payload) => $event, \Closure::class);

        $handler->fail($exception);

        self::assertTrue($handler->hasFailed());
    }

    public function testRelease()
    {
        $handler = new Handler(
            m::mock(Container::class),
            new Message('some.event', new Payload([])),
            static fn($event, $payload) => $event,
            $this->listenerClass,
            $transport = m::spy(Transport::class)
        );

        $handler->release(10);

        $transport->shouldHaveReceived()
            ->send(m::type(Message::class));

        self::assertTrue($handler->isReleased());
    }

    public function testGetAttempts()
    {
        $handler = new Handler(
            m::mock(Container::class),
            $this->getMessage()->increaseAttempts(),
            static fn($event, $payload) => $event,
            $this->listenerClass,
            m::mock(Transport::class)
        );

        self::assertEquals(1, $handler->attempts());
    }

    protected function getMessage(): Message
    {
        return new Message($this->event, new Payload(['id' => 1]));
    }

    protected function getHandler(?callable $callback = null, ?string $listenerClass = null)
    {
        return new Handler(
            m::mock(Container::class),
            $this->getMessage(),
            $callback ?: static fn($event, $payload) => $event,
            $listenerClass ?: $this->listenerClass,
            m::spy(Transport::class)
        );
    }
}

class FailingListener
{
    public $payload;

    public function failed($payload, $exception)
    {
        $this->payload = $payload;

        throw $exception;
    }
}
