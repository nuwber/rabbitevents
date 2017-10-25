<?php

namespace Nuwber\Events;

use Illuminate\Container\Container;
use Interop\Queue\PsrConsumer;
use Interop\Queue\PsrContext;
use Interop\Queue\PsrMessage;
use Mockery as m;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

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

        $message = m::mock(PsrMessage::class);
        $message->shouldReceive('getBody')->andReturn('{"id": 1}');

        $this->job = new Job(
            m::mock(Container::class),
            m::mock(PsrContext::class),
            m::mock(PsrConsumer::class),
            $message,
            $this->connectionName,
            $this->event,
            $this->listenerClass,
            $callback
        );
    }

    public function tearDown()
    {
        m::close();
    }

    public function testFire()
    {
        Assert::assertEquals("Event: $this->event. Item id: 1", $this->job->fire());
    }

    public function testGetName()
    {
        $expectedMessage =  "$this->connectionName: $this->event:$this->listenerClass";

        Assert::assertEquals($expectedMessage, $this->job->getName());
    }

}
