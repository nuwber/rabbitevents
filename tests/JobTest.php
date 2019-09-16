<?php

namespace Nuwber\Events\Tests;

use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpMessage;
use Mockery as m;
use Nuwber\Events\Job;

class JobTest extends TestCase
{
    private $job;
    private $event = 'event.called';
    private $listenerClass = 'ListenerClass';
    private $jobId = 124567;

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
        $expectedMessage =  "$this->event:$this->listenerClass";

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

    public function testGetJobId()
    {
        $job = $this->getJob(function() {});

        $this->assertEquals($this->jobId, $job->getJobId());
    }

    public function testAcknowledge()
    {
        $message = $this->getMessage();
        $consumer = m::mock(AmqpConsumer::class);
        $consumer->shouldReceive('acknowledge')
            ->with($message)
            ->once();

        $job = new Job(
            m::mock(AmqpContext::class),
            $consumer,
            $message,
            function() {},
            $this->listenerClass
        );

        $this->assertNull($job->delete());
    }

    protected function getMessage(): AmqpMessage
    {
        $message = m::mock(AmqpMessage::class);
        $message->shouldReceive('getBody')
            ->andReturn('{"id": 1}');

        $message->shouldReceive('getRoutingKey')
            ->andReturn($this->event);

        $message->shouldReceive('getMessageId')
            ->andReturn($this->jobId);

        return $message;
    }

    protected function getJob(callable $callback)
    {
        return new Job(
            m::mock(AmqpContext::class),
            m::mock(AmqpConsumer::class),
            $this->getMessage(),
            $callback,
            $this->listenerClass
        );
    }
}
