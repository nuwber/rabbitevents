<?php

namespace RabbitEvents\Tests\Listener;

use Illuminate\Container\Container;
use RabbitEvents\Foundation\Contracts\Transport;
use RabbitEvents\Foundation\Message;
use RabbitEvents\Listener\Dispatcher;
use RabbitEvents\Listener\Message\Handler;
use RabbitEvents\Tests\Listener\Stubs\ListenerStubForMiddleware;
use RabbitEvents\Tests\Listener\Stubs\ListenerWithAttributeMiddleware;
use RabbitEvents\Tests\Listener\Stubs\ListenerWithMethodMiddleware;
use RabbitEvents\Tests\Listener\Stubs\ListenerWithMixOfMiddleware;

class MiddlewareTest extends TestCase
{
    public function testExecuteMiddlewareAsMethod(): void
    {
        $message = $this->makeMessage(true);

        self::assertEquals(1, $this->makeHandler($message, $this->makeCallback(ListenerWithMethodMiddleware::class))->handle());

        // Wildcard
        self::assertEquals(1, $this->makeHandler($message, $this->makeCallback(ListenerWithMethodMiddleware::class, true))->handle());
    }

    public function testMiddlewareReturnedFalse(): void
    {
        $message = $this->makeMessage(false);

        self::assertNull($this->makeHandler($message, $this->makeCallback(ListenerWithMethodMiddleware::class))->handle());

        // Wildcard
        self::assertNull($this->makeHandler($message, $this->makeCallback(ListenerWithMethodMiddleware::class, true))->handle());
    }

    public function testExecuteMiddlewareAsArgument(): void
    {
        $message = $this->makeMessage(true);

        self::assertEquals(2, $this->makeHandler($message, $this->makeCallback(ListenerWithAttributeMiddleware::class))->handle());

        // Wildcard
        self::assertEquals(2, $this->makeHandler($message, $this->makeCallback(ListenerWithAttributeMiddleware::class, true))->handle());
    }

    public function testMiddlewareAsArgReturnedFalse(): void
    {
        $message = $this->makeMessage(false);

        self::assertNull($this->makeHandler($message, $this->makeCallback(ListenerWithAttributeMiddleware::class))->handle());

        // Wildcard
        self::assertNull($this->makeHandler($message, $this->makeCallback(ListenerWithAttributeMiddleware::class, true))->handle());
    }

    public function testAllKindOfMiddlewareTogether(): void
    {
         $message = $this->makeMessage(true);

        self::assertEquals(3, $this->makeHandler($message, $this->makeCallback(ListenerWithMixOfMiddleware::class))->handle());

        // Wildcard
        self::assertEquals(3, $this->makeHandler($message, $this->makeCallback(ListenerWithMixOfMiddleware::class, true))->handle());
    }

    public function testAllKindOfMiddlewareTogetherReturnedFalse(): void
    {
        $message = $this->makeMessage(false);

        self::assertNull($this->makeHandler($message, $this->makeCallback(ListenerWithMixOfMiddleware::class))->handle());

        // Wildcard
        self::assertNull($this->makeHandler($message, $this->makeCallback(ListenerWithMixOfMiddleware::class, true))->handle());
    }

    /**
     * Test whether middleware receive correct array structure
     */
    public function testMiddlewareReceiveAssociativeArray(): void
    {
        $payload = ['first' => '1'];
        //incomming message payload should always be wrapped in array
        $message = $this->makeMessage([$payload]);

        self::assertEquals([$payload], $this->makeHandler($message, $this->makeCallback(ListenerStubForMiddleware::class))->handle());

        // Wildcard
        self::assertEquals(['event', $payload], $this->makeHandler($message, $this->makeCallback(ListenerStubForMiddleware::class, true))->handle());
    }

    private function makeCallback($listenerClass, $wildcard = false): callable
    {
        return (new Dispatcher())->makeListener($listenerClass, $wildcard);
    }

    private function makeMessage(mixed $payload): Message
    {
        return new Message('event', new Payload($payload), \Mockery::mock(Transport::class));
    }

    private function makeHandler($message, $callback): Handler
    {
        return new Handler(new Container(), $message, $callback, __CLASS__);
    }
}

