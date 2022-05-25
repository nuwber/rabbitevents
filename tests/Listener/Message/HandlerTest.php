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
            'trim',
            $this->listenerClass
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

        $handler = new Handler($container, $message, 'trim', FailingListener::class);

        $handler->fail($exception);

        self::assertTrue($handler->hasFailed());
        self::assertEquals($message->payload(), $listener->payload);
    }

    public function testFailClosure(): void
    {
        $exception = new \Exception("Exception in `fire` method");

        $handler = $this->getHandler('trim', \Closure::class);

        $handler->fail($exception);

        self::assertTrue($handler->hasFailed());
    }

    public function testRelease()
    {
        $message = m::spy(Message::class);

        $handler = new Handler(
            m::mock(Container::class),
            $message,
            'trim',
            $this->listenerClass
        );

        $handler->release(10);

        $message->shouldHaveReceived()
            ->release(10)
            ->once();

        self::assertTrue($handler->isReleased());
    }

    public function testGetAttempts()
    {
        $handler = new Handler(
            m::mock(Container::class),
            $this->getMessage()->increaseAttempts(),
            'trim',
            $this->listenerClass
        );

        self::assertEquals(1, $handler->attempts());
    }

    protected function getMessage(): Message
    {
        return new Message($this->event, new Payload(['id' => 1]), m::mock(Transport::class));
    }

    protected function getHandler(?callable $callback = null, ?string $listenerClass = null)
    {
        return new Handler(
            m::mock(Container::class),
            $this->getMessage(),
            $callback ?: 'trim',
            $listenerClass ?: $this->listenerClass
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
