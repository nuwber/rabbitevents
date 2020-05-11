<?php

namespace Nuwber\Events\Tests\Queue;

use Interop\Amqp\Impl\AmqpMessage;
use Nuwber\Events\Dispatcher;
use Nuwber\Events\Queue\Job;
use Nuwber\Events\Tests\Queue\Stubs\ListenerWithAttributeMiddleware;
use Nuwber\Events\Tests\Queue\Stubs\ListenerWithMethodMiddleware;
use Nuwber\Events\Tests\Queue\Stubs\ListenerWithMixOfMiddleware;
use Nuwber\Events\Tests\TestCase;

class MiddlewareTest extends TestCase
{
    public function testExecuteMiddlewareAsMethod()
    {
        $message = $this->makeMessage('true');

        $this->assertEquals(1, $this->makeJob($message, $this->makeCallback(ListenerWithMethodMiddleware::class))->fire());

        // Wildcard
        $this->assertEquals(1, $this->makeJob($message, $this->makeCallback(ListenerWithMethodMiddleware::class, true))->fire());
    }

    public function testMiddlewareReturnedFalse()
    {
        $message = $this->makeMessage('false');

        $this->assertNull($this->makeJob($message, $this->makeCallback(ListenerWithMethodMiddleware::class))->fire());

        // Wildcard
        $this->assertNull($this->makeJob($message, $this->makeCallback(ListenerWithMethodMiddleware::class, true))->fire());
    }

    public function testExecuteMiddlewareAsArgument()
    {
        $message = $this->makeMessage('true');

        $this->assertEquals(2, $this->makeJob($message, $this->makeCallback(ListenerWithAttributeMiddleware::class))->fire());

        // Wildcard
        $this->assertEquals(2, $this->makeJob($message, $this->makeCallback(ListenerWithAttributeMiddleware::class, true))->fire());
    }

    public function testMiddlewareAsArgReturnedFalse()
    {
        $message = $this->makeMessage('false');

        $this->assertNull($this->makeJob($message, $this->makeCallback(ListenerWithAttributeMiddleware::class))->fire());

        // Wildcard
        $this->assertNull($this->makeJob($message, $this->makeCallback(ListenerWithAttributeMiddleware::class, true))->fire());
    }

    public function testAllKindOfMiddlewareTogether()
    {
         $message = $this->makeMessage('true');

        $this->assertEquals(3, $this->makeJob($message, $this->makeCallback(ListenerWithMixOfMiddleware::class))->fire());

        // Wildcard
        $this->assertEquals(3, $this->makeJob($message, $this->makeCallback(ListenerWithMixOfMiddleware::class, true))->fire());
    }

    public function testAllKindOfMiddlewareTogetherReturnedFalse()
    {
        $message = $this->makeMessage('false');

        $this->assertNull($this->makeJob($message, $this->makeCallback(ListenerWithMixOfMiddleware::class))->fire());

        // Wildcard
        $this->assertNull($this->makeJob($message, $this->makeCallback(ListenerWithMixOfMiddleware::class, true))->fire());
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
            \Mockery::mock('Illuminate\Container\Container'),
            \Mockery::mock('Interop\Amqp\AmqpContext'),
            \Mockery::mock('Interop\Amqp\AmqpConsumer'),
            $message,
            $callback,
            __CLASS__
        );
    }
}
