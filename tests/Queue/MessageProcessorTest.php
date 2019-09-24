<?php

namespace Nuwber\Events\Tests\Queue;

use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Events\Dispatcher as Events;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Interop\Amqp\Impl\AmqpMessage;
use Mockery as m;
use Nuwber\Events\Queue\Exceptions\FailedException;
use Nuwber\Events\Queue\Job;
use Nuwber\Events\Queue\JobsFactory;
use Nuwber\Events\Queue\MessageProcessor;
use Nuwber\Events\Queue\ProcessingOptions;
use Nuwber\Events\Tests\TestCase;

class MessageProcessorTest extends TestCase
{
    private $message;
    private $options;
    private $events;
    private $exceptionHandler;

    public function setUp()
    {
        $this->events = m::spy(Events::class);
        $this->exceptionHandler = m::spy(ExceptionHandler::class);

        Container::setInstance($container = new Container);
        $container->instance(Dispatcher::class, $this->events);
        $container->instance(ExceptionHandler::class, $this->exceptionHandler);

        $this->options = new ProcessingOptions(128, 60, 0, 5, 'test-app', 'interop');

        $this->message = new AmqpMessage();
    }

    public function testIsInitialisable()
    {
        $this->assertInstanceOf(MessageProcessor::class, $this->getProcessor());
    }

    public function testProcess()
    {
        $job = new FakeJob();

        $this->getProcessor([$job])->process($this->message);

        $this->assertTrue($job->fired);
    }

    public function testPropaginationStopped()
    {
        $jobMock = m::mock(Job::class);
        $jobMock->shouldReceive('fire')
            ->andReturn(false);
        $job = new FakeJob();

        $this->getProcessor([$jobMock, $job])
            ->process($this->message);

        $this->assertFalse($job->fired);
    }

    public function testProcessFail()
    {
        $e = new FailedException();

        $job = new FakeJob(
            function () use ($e) {
                throw $e;
            }
        );

        $this->getProcessor([$job])->process($this->message);

        $this->assertTrue($job->failed);
        $this->assertTrue($job->deleted);
        $this->assertFalse($job->released);

        $this->exceptionHandler->shouldHaveReceived('report')->with($e)->once();
    }

    public function testRunJob()
    {
        $job = new FakeJob();

        $this->getProcessor()->runJob($job);

        $this->assertTrue($job->fired);
        $this->events->shouldHaveReceived('dispatch')->with(m::type(JobProcessing::class))->once();
        $this->events->shouldHaveReceived('dispatch')->with(m::type(JobProcessed::class))->once();
    }

    public function testRunJobReleased()
    {
        $exceptionMessage = 'Failed job exception';
        $this->expectExceptionMessage($exceptionMessage);

        $job = new FakeJob(
            function () use ($exceptionMessage) {
                throw new \Exception($exceptionMessage);
            }
        );

        $this->getProcessor()->runJob($job);

        $this->assertTrue($job->failed);
        $this->assertTrue($job->released);
        $this->assertTrue($job->deleted);

        $this->events->shouldHaveReceived('dispatch')->with(m::type(JobProcessing::class))->once();
        $this->events->shouldHaveReceived('dispatch')->with(m::type(JobExceptionOccurred::class))->once();
        $this->events->shouldNotHaveReceived('dispatch')->with(m::type(JobProcessed::class));
    }

    public function testRunJobFailed()
    {
        $exceptionMessage = 'Failed job exception';
        $this->expectExceptionMessage($exceptionMessage);

        $job = new FakeJob(
            function () use ($exceptionMessage) {
                throw new FailedException($exceptionMessage);
            }
        );

        $this->getProcessor()->runJob($job);

        $this->assertTrue($job->released);
        $this->assertTrue($job->failed);
        $this->assertTrue($job->deleted);

        $this->events->shouldHaveReceived('dispatch')->with(m::type(JobProcessing::class))->once();
        $this->events->shouldHaveReceived('dispatch')->with(m::type(JobFailed::class))->once();
        $this->events->shouldNotHaveReceived('dispatch')->with(m::type(JobProcessed::class));
    }

    protected function getProcessor(array $jobs = [])
    {
        $factory = m::mock(JobsFactory::class);
        $factory->shouldReceive('make')
            ->andReturn($jobs);

        return new MessageProcessor(
            $this->events,
            $this->exceptionHandler,
            $factory,
            $this->options
        );
    }
}

class FakeJob extends Job
{
    public $fired = false;
    public $callback;
    public $deleted = false;
    public $releaseAfter;
    public $released = false;
    public $maxTries;
    public $timeoutAt;
    public $attempts = 0;
    public $failedWith;
    public $failed = false;
    public $connectionName;

    public function __construct($callback = null)
    {
        $this->callback = $callback ?: function () {
        };
    }

    public function fire()
    {
        $this->fired = true;
        $this->callback->__invoke($this);
    }

    public function payload()
    {
        return [];
    }

    public function maxTries()
    {
        return $this->maxTries;
    }

    public function timeoutAt()
    {
        return $this->timeoutAt;
    }

    public function delete()
    {
        $this->deleted = true;
    }

    public function isDeleted()
    {
        return $this->deleted;
    }

    public function release($delay = 0)
    {
        $this->released = true;
        $this->releaseAfter = $delay;
    }

    public function isReleased()
    {
        return $this->released;
    }

    public function attempts()
    {
        return $this->attempts;
    }

    public function markAsFailed()
    {
        $this->failed = true;
    }

    public function failed($e)
    {
        $this->markAsFailed();
        $this->failedWith = $e;
    }

    public function hasFailed()
    {
        return $this->failed;
    }

    public function resolveName()
    {
        return 'FakeJob';
    }

    public function setConnectionName($name)
    {
        $this->connectionName = $name;
    }

    public function resolve($class)
    {
        return Container::getInstance()->make($class);
    }
}
