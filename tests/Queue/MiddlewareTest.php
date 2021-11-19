<?php

namespace Nuwber\Events\Tests\Queue;

use Illuminate\Container\Container;
use Nuwber\Events\Queue\Jobs\Job;
use Nuwber\Events\Dispatcher;
use Nuwber\Events\Queue\Manager;
use Nuwber\Events\Tests\TestCase;
use Interop\Amqp\Impl\AmqpMessage;
use Nuwber\Events\Tests\Queue\Stubs\ListenerStubForMiddleware;
use Nuwber\Events\Tests\Queue\Stubs\ListenerWithMixOfMiddleware;
use Nuwber\Events\Tests\Queue\Stubs\ListenerWithMethodMiddleware;
use Nuwber\Events\Tests\Queue\Stubs\ListenerWithAttributeMiddleware;

class MiddlewareTest extends TestCase
{
    public function testExecuteMiddlewareAsMethod()
    {
        $message = $this->makeMessage('true');

        self::assertEquals(1, $this->makeJob($message, $this->makeCallback(ListenerWithMethodMiddleware::class))->fire());

        // Wildcard
        self::assertEquals(1, $this->makeJob($message, $this->makeCallback(ListenerWithMethodMiddleware::class, true))->fire());
    }

    public function testMiddlewareReturnedFalse()
    {
        $message = $this->makeMessage('false');

        self::assertNull($this->makeJob($message, $this->makeCallback(ListenerWithMethodMiddleware::class))->fire());

        // Wildcard
        self::assertNull($this->makeJob($message, $this->makeCallback(ListenerWithMethodMiddleware::class, true))->fire());
    }

    public function testExecuteMiddlewareAsArgument()
    {
        $message = $this->makeMessage('true');

        self::assertEquals(2, $this->makeJob($message, $this->makeCallback(ListenerWithAttributeMiddleware::class))->fire());

        // Wildcard
        self::assertEquals(2, $this->makeJob($message, $this->makeCallback(ListenerWithAttributeMiddleware::class, true))->fire());
    }

    public function testMiddlewareAsArgReturnedFalse()
    {
        $message = $this->makeMessage('false');

        self::assertNull($this->makeJob($message, $this->makeCallback(ListenerWithAttributeMiddleware::class))->fire());

        // Wildcard
        self::assertNull($this->makeJob($message, $this->makeCallback(ListenerWithAttributeMiddleware::class, true))->fire());
    }

    public function testAllKindOfMiddlewareTogether()
    {
         $message = $this->makeMessage('true');

        self::assertEquals(3, $this->makeJob($message, $this->makeCallback(ListenerWithMixOfMiddleware::class))->fire());

        // Wildcard
        self::assertEquals(3, $this->makeJob($message, $this->makeCallback(ListenerWithMixOfMiddleware::class, true))->fire());
    }

    public function testAllKindOfMiddlewareTogetherReturnedFalse()
    {
        $message = $this->makeMessage('false');

        self::assertNull($this->makeJob($message, $this->makeCallback(ListenerWithMixOfMiddleware::class))->fire());

        // Wildcard
        self::assertNull($this->makeJob($message, $this->makeCallback(ListenerWithMixOfMiddleware::class, true))->fire());
    }

    /**
     * Test whether middleware receive correct array structure
     */
    public function testMiddlewareReceiveAssociativeArray()
    {
        //incomming message payload should always be wrapped in array
        $message = $this->makeMessage('[{"first":"1"}]');

        $expectedResult = ['first' => '1'];

        self::assertEquals([$expectedResult], $this->makeJob($message, $this->makeCallback(ListenerStubForMiddleware::class))->fire());

        // Wildcard
        self::assertEquals(['event', $expectedResult], $this->makeJob($message, $this->makeCallback(ListenerStubForMiddleware::class, true))->fire());
    }

    private function makeCallback($listenerClass, $wildcard = false)
    {
        return (new Dispatcher())->makeListener($listenerClass, $wildcard);
    }

    private function makeMessage($payload)
    {
        $message = new AmqpMessage();
        $message->setBody($payload);
        $message->setRoutingKey('event');

        return $message;
    }

    private function makeJob($message, $callback)
    {
        return new Job(
            new Container(),
            \Mockery::spy(Manager::class),
            $message,
            $callback,
            __CLASS__
        );
    }
}
