<?php

namespace Nuwber\Events\Tests\Queue;

use Illuminate\Container\Container;
use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpMessage;
use Mockery as m;
use Nuwber\Events\Event\Publisher;
use Nuwber\Events\Queue\Job;
use Nuwber\Events\Tests\TestCase;

class JobTest extends TestCase
{
    private $job;
    private $event = 'event.called';
    private $listenerClass = 'ListenerClass';
    private $jobId = 124567;

    public function setUp(): void
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
        $this->expectNotToPerformAssertions();

        $message = $this->getMessage();
        $consumer = m::mock(AmqpConsumer::class);
        $consumer->shouldReceive('acknowledge')
            ->with($message)
            ->once();

        $job = new Job(
            m::mock(Container::class),
            m::mock(AmqpContext::class),
            $consumer,
            $message,
            function() {},
            $this->listenerClass
        );

        $job->delete();
    }

    public function testRelease()
    {
        $this->expectNotToPerformAssertions();

        $container = m::mock(Container::class);
        $publisher = m::mock(Publisher::class);

        $container->shouldReceive('make')
            ->with(Publisher::class)
            ->andReturn($publisher);

        $publisher->shouldReceive('sendMessage')
            ->once()
            ->withAnyArgs()
            ->andReturnSelf();

        $message = m::mock(AmqpMessage::class);
        $message->shouldReceive('getProperty')
            ->once()
            ->andReturn(0);
        $message->shouldReceive('getRoutingKey')
            ->andReturn($this->event);

        $message->shouldReceive('setProperty')
            ->with('x-attempts', 1)
            ->once();

        $job = new Job(
            $container,
            m::mock(AmqpContext::class),
            m::mock(AmqpConsumer::class),
            $message,
            function() {},
            $this->listenerClass
        );

        $job->release();
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
            m::mock(Container::class),
            m::mock(AmqpContext::class),
            m::mock(AmqpConsumer::class),
            $this->getMessage(),
            $callback,
            $this->listenerClass
        );
    }
}
