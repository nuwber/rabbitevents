<?php

namespace Nuwber\Events\Tests\Queue\Message;

use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Events\Dispatcher as Events;
use Nuwber\Events\Queue\Events\JobExceptionOccurred;
use Nuwber\Events\Queue\Events\JobFailed;
use Nuwber\Events\Queue\Events\JobProcessing;
use Nuwber\Events\Queue\Events\JobProcessed;
use Interop\Amqp\Impl\AmqpMessage;
use Nuwber\Events\Queue\Exceptions\FailedException;
use Nuwber\Events\Queue\Jobs\Factory;
use Nuwber\Events\Queue\Jobs\Job;
use Nuwber\Events\Queue\Message\Processor;
use Nuwber\Events\Queue\ProcessingOptions;
use Nuwber\Events\Tests\TestCase;
use Mockery as m;

class ProcessorTest extends TestCase
{
    private $message;
    private $events;

    public function setUp(): void
    {
        $this->events = m::spy(Events::class);

        Container::setInstance($container = new Container);
        $container->instance(Dispatcher::class, $this->events);

        $this->message = new AmqpMessage();
    }

    public function testProcess()
    {
        $job = new FakeJob();

        $processor = new Processor($this->events, $this->makeJobsFactory([$job]));

        $processor->process($this->message, $this->options());

        self::assertTrue($job->fired);
    }

    public function testPropaginationStopped()
    {
        $stoppingJob = new FakeJob(function() { return false; });

        $job = new FakeJob();

        $jobFactory = $this->makeJobsFactory([$stoppingJob, $job]);

        $processor = new Processor($this->events, $jobFactory);

        $processor->process($this->message, $this->options());

        self::assertFalse($job->fired);
    }

    public function testProcessFail()
    {
        $this->expectException(FailedException::class);

        $job = new FakeJob(function () {
            throw new FailedException();
        });

        $processor = new Processor($this->events, $this->makeJobsFactory([$job]));

        $processor->process($this->message, $this->options());

        self::assertTrue($job->failed);
        self::assertTrue($job->deleted);
        self::assertFalse($job->released);
    }

    public function testRunJob()
    {
        $job = new FakeJob();

        $processor = new Processor($this->events, $this->makeJobsFactory([]));

        $processor->runJob($job, $this->options());

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

        $processor = new Processor($this->events, $this->makeJobsFactory([]));

        $processor->runJob($job, $this->options());

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

        $processor = new Processor($this->events, $this->makeJobsFactory([]));

        $processor->runJob($job, $this->options());

        $this->assertTrue($job->released);
        $this->assertTrue($job->failed);
        $this->assertTrue($job->deleted);

        $this->events->shouldHaveReceived('dispatch')->with(m::type(JobProcessing::class));
        $this->events->shouldHaveReceived('dispatch')->with(m::type(JobFailed::class));
        $this->events->shouldNotHaveReceived('dispatch')->with(m::type(JobProcessed::class));
    }

    public function testJobIsNotReleasedIfItHasExceededMaxAttempts()
    {
        $e = new \RuntimeException;
        self::expectException('Illuminate\Queue\MaxAttemptsExceededException');

        $job = new FakeJob(
            function ($job) use ($e) {
                // In normal use this would be incremented by being popped off the queue
                $job->attempts++;

                // and this exception shouldn't be thrown
                throw $e;
            }
        );
        $job->attempts = 2;

        $processor = new Processor($this->events, $this->makeJobsFactory([]));
        $processor->runJob($job, $this->options(['maxTries' => 1]));

        self::assertTrue($job->failed);
        self::assertTrue($job->deleted);
        self::assertFalse($job->released);
        $this->events->shouldHaveReceived('dispatch')->with(m::type(JobFailed::class));
        self::assertSame($e, $job->failedWith);
    }

    protected function options(array $overrides = [])
    {
        $options = new ProcessingOptions('test-app', 'rabbitmq');

        foreach ($overrides as $key => $value) {
            $options->{$key} = $value;
        }

        return $options;

    }
    
    protected function makeJobsFactory(array $jobs)
    {
        $callback = static function ($jobs) {
            foreach ((array)$jobs as $job) {
                yield $job;
            }
        };

        $jobFactory = m::mock(Factory::class);
        $jobFactory->shouldReceive('makeJobs')
            ->andReturn($callback($jobs));

        return $jobFactory;
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
    public $acknowledged = false;

    public function __construct(callable $callback = null)
    {
        $this->callback = $callback ?: function () {
        };
    }

    public function fire()
    {
        $this->fired = true;
        return call_user_func($this->callback, $this);
    }

    public function payload()
    {
        return [];
    }

    public function maxTries()
    {
        return $this->maxTries;
    }

    public function timeoutAt(): ?int
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

    public function release($delay = 0): void
    {
        $this->released = true;
        $this->releaseAfter = $delay;
    }

    public function isReleased()
    {
        return $this->released;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function markAsFailed()
    {
        $this->failed = true;
    }

    public function failed($e): void
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

    public function __destruct()
    {
        $this->acknowledged = true;
    }
}
