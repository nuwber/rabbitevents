<?php

namespace Nuwber\Events\Tests;

use Illuminate\Container\Container;
use Interop\Amqp\AmqpMessage;
use Interop\Queue\PsrConsumer;
use Interop\Queue\PsrContext;
use Mockery as m;
use Nuwber\Events\Job;

class JobTest extends TestCase
{
    private $job;
    private $connectionName = 'interop';
    private $event = 'event.called';
    private $listenerClass = 'ListenerClass';

    public function setUp()
    {
        $callback = function ($event, $payload) {
            return "Event: $event. Item id: {$payload['id']}";
        };

        $this->job = $this->getJob($callback);
    }

    public function testFire()
    {
        self::assertEquals("Event: $this->event. Item id: 1", $this->job->fire());
    }

    public function testGetName()
    {
        $expectedMessage =  "$this->connectionName:$this->event:$this->listenerClass";

        self::assertEquals($expectedMessage, $this->job->getName());
    }

    public function testFailed()
    {
        $class = new class() {

            public function fire()
            {
                throw new \Exception("Exception in fire");
            }

            public function failed($exception)
            {
                throw $exception;
            }
        };

        $callback = function () use ($class) {
            return call_user_func_array([new $class, 'fire'], []);
        };

        $job = $this->getJob($callback);

        $this->expectExceptionMessage("Exception in fire");

        $job->fire();
    }

    protected function getMessage(): AmqpMessage
    {
        $message = m::mock(AmqpMessage::class);
        $message->shouldReceive('getBody')
            ->andReturn('{"id": 1}');

        $message->shouldReceive('getRoutingKey')
            ->andReturn($this->event);

        return $message;
    }

    protected function getJob(callable $callback)
    {
        return new Job(
            m::mock(Container::class),
            m::mock(PsrContext::class),
            m::mock(PsrConsumer::class),
            $this->getMessage(),
            $this->connectionName,
            $callback,
            $this->listenerClass
        );
    }
}
