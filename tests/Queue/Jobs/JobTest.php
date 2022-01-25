<?php

namespace Nuwber\Events\Tests\Queue\Jobs;

use Enqueue\AmqpLib\AmqpConsumer;
use Illuminate\Container\Container;
use Interop\Amqp\AmqpMessage;
use Mockery as m;
use Nuwber\Events\Queue\Jobs\Job;
use Nuwber\Events\Queue\Manager;
use Nuwber\Events\Queue\Message\Transport;
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
        $manager = new FakeManager();
        $manager->event = $this->event;

        $job = new Job(
            m::mock(Container::class),
            $manager,
            $this->getMessage(),
            'trim',
            $this->listenerClass
        );

        self::assertEquals("test-app:{$this->event}:{$this->listenerClass}", $job->getName());
    }

    public function testExceptionFired()
    {
        $class = new class() {
            public function fire()
            {
                throw new \Exception("Exception in `fire` method");
            }
        };

        $callback = function () use ($class) {
            return call_user_func_array([new $class, 'fire'], []);
        };

        $job = $this->getJob($callback);

        self::expectExceptionMessage("Exception in `fire` method");

        $job->fire();
    }

    public function testFailing()
    {
        $exception = new \Exception("Exception in `fire` method");

        self::expectExceptionMessage($exception->getMessage());

        $listener = new FailingListener();

        $app = new Container();
        $app->instance(FailingListener::class, $listener);
        $message = $this->getMessage();

        $queueManager = m::spy(Manager::class);

        $job = new Job($app, $queueManager, $message,
            'trim',
            FailingListener::class
        );

        $job->failed($exception);

        $queueManager->shouldHaveReceived()
            ->acknowledge($message)
            ->once();

        self::assertTrue($job->hasFailed());
        self::assertEquals($message->getBody(), $listener->payload);
    }

    public function testFailingClosure()
    {
        $exception = new \Exception("Exception in `fire` method");

        $app = new Container();
        $message = $this->getMessage();

        $queueManager = m::spy(Manager::class);

        $job = new Job($app, $queueManager, $message,
            'trim',
            \Closure::class
        );

        $job->failed($exception);

        self::assertTrue($job->hasFailed());
    }

    public function testGetJobId()
    {
        $job = $this->getJob();

        self::assertEquals($this->jobId, $job->getJobId());
    }

    public function testRelease()
    {
        $message = $this->getMessage();

        $manager = new FakeManager();

        $job = new Job(
            m::mock(Container::class),
            $manager,
            $message,
            'trim',
            $this->listenerClass
        );

        self::assertEquals(1, $job->attempts());

        $job->release(10);

        self::assertInstanceOf(AmqpMessage::class, $manager->message);
        self::assertNotSame($message, $manager->message);
        self::assertEquals(1, $job->attempts());
        self::assertEquals(2, $manager->message->getProperty('x-attempts'));
        self::assertEquals(10, $manager->delay);
    }

    public function testGetQueue()
    {
        $manager = new FakeManager();
        $manager->event = $this->event;

        $job = new Job(
            m::mock(Container::class),
            $manager,
            $this->getMessage(),
            'trim',
            $this->listenerClass
        );

        self::assertEquals("test-app:{$this->event}", $job->getQueue());
    }

    protected function getMessage(): AmqpMessage
    {
        $message = new \Interop\Amqp\Impl\AmqpMessage('{"id": 1}',);
        $message->setRoutingKey($this->event);
        $message->setMessageId($this->jobId);

        return $message;
    }

    protected function getJob(?callable $callback = null, ?string $listenerClass = null)
    {
        return new Job(
            m::mock(Container::class),
            m::spy(Manager::class),
            $this->getMessage(),
            $callback ?: 'trim',
            $listenerClass ?: $this->listenerClass
        );
    }
}

class FailingListener
{
    public $payload;

    public function failed($payload, $exception)
    {
        $this->payload = $payload;

        throw $exception;
    }
}

class FakeManager extends Manager
{
    public $message;
    public $delay;
    public $released = false;
    public $acknowledged = false;
    public $event;
    public $consumer;

    public function __construct()
    {
        parent::__construct(m::spy(AmqpConsumer::class), m::spy(Transport::class));
    }

    public function send(AmqpMessage $message, int $delay = 0): void
    {
        $this->message = $message;
        $this->delay = $delay;
    }

    public function release(AmqpMessage $message, int $attempts = 1, int $delay = 0): void
    {
        $this->released = true;
        parent::release($message, $attempts, $delay);
    }

    public function acknowledge(AmqpMessage $message): void
    {
        $this->acknowledged = true;
        parent::acknowledge($message);
    }

    public function getEvent(): string
    {
        return 'test-app:' . $this->event;
    }
}
