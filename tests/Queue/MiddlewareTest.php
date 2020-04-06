<?php

namespace Nuwber\Events\Tests\Queue;

use Illuminate\Support\Arr;
use Interop\Amqp\Impl\AmqpMessage;
use Nuwber\Events\Dispatcher;
use Nuwber\Events\Queue\Job;
use Nuwber\Events\Tests\TestCase;

class MiddlewareTest extends TestCase
{

    public function testExecuteMiddlewareAsMethod()
    {
        $message = $this->makeMessage('true');

        $expectation = 'Middleware was called';

        $this->assertEquals($expectation, $this->makeJob($message, $this->makeCallback(MethodMiddleware::class))->fire());

        // Wildcard
        $this->assertEquals($expectation, $this->makeJob($message, $this->makeCallback(MethodMiddleware::class, true))->fire());
    }

    public function testMiddlewareReturnedFalse()
    {
        $message = $this->makeMessage('false');
        $expectation = '';

        $this->assertEquals($expectation, $this->makeJob($message, $this->makeCallback(MethodMiddleware::class))->fire());

        // Wildcard
        $this->assertEquals($expectation, $this->makeJob($message, $this->makeCallback(MethodMiddleware::class, true))->fire());
    }

    public function testExecuteMiddlewareAsArgument()
    {
        $message = $this->makeMessage('true');

        $expectation = 'Middleware was called';

        $this->assertEquals($expectation, $this->makeJob($message, $this->makeCallback(AttributeMiddleware::class))->fire());

        // Wildcard
        $this->assertEquals($expectation, $this->makeJob($message, $this->makeCallback(AttributeMiddleware::class, true))->fire());
    }

    public function testMiddlewareAsArgReturnedFalse()
    {
        $this->assertEquals('', $this->makeJob($this->makeMessage('false'), $this->makeCallback(AttributeMiddleware::class))->fire());

        // Wildcard
        $this->assertEquals('', $this->makeJob($this->makeMessage('false'), $this->makeCallback(AttributeMiddleware::class, true))->fire());
    }

    private function makeCallback($listenerClass, $wildcard = false)
    {
        return (new Dispatcher())->createClassListener($listenerClass, $wildcard);
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

class MethodMiddleware
{
    private $middlewareWasCalled = false;

    public function handle($payload)
    {
        return "Middleware was " . ($this->middlewareWasCalled ? '' : ' not ') . 'called';
    }

    public function middleware($payload)
    {
        $this->middlewareWasCalled = true;

        return Arr::first($payload);
    }
}

class AttributeMiddleware
{
    public $middleware = [GlobalMiddleware::class, 'action'];

    public function handle($payload)
    {
        return "Middleware was " . (GlobalMiddleware::$middlewareWasCalled ? '' : ' not ') . 'called';
    }
}

class GlobalMiddleware
{
    public static $middlewareWasCalled = false;

    public static function action($payload)
    {
        self::$middlewareWasCalled = true;

        return Arr::first($payload);
    }
}
