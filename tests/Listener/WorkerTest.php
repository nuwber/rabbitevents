<?php

namespace RabbitEvents\Tests\Listener;

use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Mockery as m;
use RabbitEvents\Foundation\Consumer;
use RabbitEvents\Foundation\Exceptions\ConnectionLostException;
use RabbitEvents\Foundation\Message;
use RabbitEvents\Listener\Dispatcher;
use RabbitEvents\Listener\Events\MessageProcessingFailed;
use RabbitEvents\Listener\Events\WorkerStopping;
use RabbitEvents\Listener\ListenerOptions;
use RabbitEvents\Listener\Message\Processor;
use RabbitEvents\Listener\Worker;
use RabbitEvents\Listener\WorkerExitStatus;
use RabbitEvents\Tests\MocksCommonObjects;

class WorkerTest extends TestCase
{
    use MocksCommonObjects;

    public $events;
    public $exceptionHandler;

    private $options;

    protected function setUp(): void
    {
        $this->events = m::spy(Dispatcher::class);
        $this->exceptionHandler = m::spy(ExceptionHandler::class);
        $this->options = $this->createListenerOptions();

        Container::setInstance($container = new Container);

        $container->instance(ExceptionHandler::class, $this->exceptionHandler);
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
    }

    protected function createMessageMock(): Message
    {
        $message = m::mock(Message::class);
        $message->shouldReceive('hasFailed')->byDefault()->andReturn(false);

        return $message;
    }

    public function testInstantiable(): void
    {
        self::assertInstanceOf(Worker::class, new Worker($this->exceptionHandler, $this->events));
    }

    public function testWork(): void
    {
        $worker = new Worker($this->exceptionHandler, $this->events);
        $worker->shouldQuit = true; //For one tick only

        $processor = m::spy(Processor::class);
        $message = $this->createMessageMock();
        $consumer = m::mock(Consumer::class)->makePartial();
        $consumer->shouldReceive('nextMessage')
            ->andReturn($message);
        $consumer->shouldReceive('acknowledge')->once();

        $status = $worker->work($processor, $consumer, $this->options);

        self::assertEquals(WorkerExitStatus::SUCCESS, $status);

        $processor->shouldHaveReceived()->process($message, $this->options);
        $this->events->shouldHaveReceived()->dispatch(m::type(WorkerStopping::class))->once();
    }

    public function testStopIfMemoryLimitExceeded(): void
    {
        $worker = new Worker($this->exceptionHandler, $this->events);
        $options = $this->createListenerOptions(['memory' => 0]);

        $consumer = m::mock(Consumer::class);
        $consumer->shouldReceive('nextMessage')
            ->andReturn($this->createMessageMock());
        $consumer->shouldReceive('acknowledge')->once();

        $status = $worker->work(m::spy(Processor::class), $consumer, $options);

        self::assertEquals(WorkerExitStatus::MEMORY_LIMIT, $status);
        $this->events->shouldHaveReceived()->dispatch(m::type(WorkerStopping::class))->once();
    }

    public function testStopListeningIfLostConnection(): void
    {
        $exception = new ConnectionLostException();

        $worker = new Worker($this->exceptionHandler, $this->events);

        $consumer = m::mock(Consumer::class);
        $consumer->shouldReceive('nextMessage')
            ->andThrow($exception);

        $status = $worker->work(m::mock(Processor::class), $consumer, $this->options);

        self::assertEquals(WorkerExitStatus::SUCCESS, $status);

        $this->exceptionHandler->shouldHaveReceived()->report($exception);
        $this->events->shouldHaveReceived()->dispatch(m::type(WorkerStopping::class))->once();
    }

    public function testFinallyAcknowledge(): void
    {
        $exception = new \RuntimeException();

        $worker = new Worker($this->exceptionHandler, $this->events);
        $worker->shouldQuit = true; //For one tick only

        $processor = m::mock(Processor::class);
        $processor->shouldReceive('process')
            ->andThrow($exception);

        $message = $this->createMessageMock();
        $consumer = m::mock(Consumer::class);
        $consumer->shouldReceive('nextMessage')
            ->andReturn($message);

        $consumer->shouldReceive()
            ->acknowledge($message);

        $status = $worker->work($processor, $consumer, $this->options);

        self::assertEquals(WorkerExitStatus::SUCCESS, $status);

        $this->exceptionHandler->shouldHaveReceived()->report($exception);
        $this->events->shouldHaveReceived()->dispatch(m::type(WorkerStopping::class))->once();
    }

    public function testProcessNotStartedIfExceededMaxAttempts(): void
    {
        $message = $this->createMessageMock();
        $message->shouldReceive('attempts')
            ->andReturn(3);
        $message->shouldReceive('markAsFailed')->once();
        $message->shouldReceive('hasFailed')->andReturn(true);

        $consumer = m::mock(Consumer::class);
        $consumer->shouldReceive()
            ->nextMessage(1000)
            ->andReturn($message);
        $consumer->shouldReceive('reject')->with($message, false)->once();

        $processor = m::spy(Processor::class);

        $worker = new Worker($this->exceptionHandler, $this->events);
        $worker->shouldQuit = true; //one tick

        $options = $this->createListenerOptions(['maxTries' => 2]);

        $worker->work($processor, $consumer, $options);

        $processor->shouldNotHaveReceived('process');

        $this->events->shouldHaveReceived()->dispatch(m::type(MessageProcessingFailed::class))->once();
    }

    public function testExitIfTimedOut(): void
    {
        $message = $this->createMessageMock();
        $message->shouldReceive('markAsFailed')->once();

        $consumer = m::mock(Consumer::class);
        $consumer->shouldReceive('nextMessage')
            ->andReturn($message);

        $consumer->shouldReceive('reject')->with($message, false)->once();
        $consumer->shouldReceive('acknowledge')->once(); //The second is because of $shouldQuit === true

        $processor = m::mock(Processor::class);
        $processor->shouldReceive('process')
            ->andReturnUsing(function () {
                sleep(2);

                return true;
            });

        $options = $this->createListenerOptions(['timeout' => 1]);

        $worker = new TestWorker($this->exceptionHandler, $this->events);
        $worker->shouldQuit = true;
        $worker->work($processor, $consumer, $options);

        self::assertEquals(WorkerExitStatus::ERROR, $worker->exitStatus);

        $this->events->shouldHaveReceived()->dispatch(m::type(MessageProcessingFailed::class))->once();
    }

    public function testTimeoutResetIfProcessEndedWithException(): void
    {
        $consumer = m::mock(Consumer::class);
        $consumer->shouldReceive('nextMessage')
            ->andReturn($this->createMessageMock());

        $consumer->shouldReceive('acknowledge')->once();

        $processor = m::mock(Processor::class);
        $processor->shouldReceive('process')
            ->andThrow(new \RuntimeException('Stopped unexpectedly'));

        $options = $this->createListenerOptions(['timeout' => 1]);

        $worker = new TestWorker($this->exceptionHandler, $this->events);
        $worker->shouldQuit = true;
        $worker->work($processor, $consumer, $options);

        self::assertTrue($worker->resetTimeoutHandler);
    }

    public function testWorkUsesFailerWhenMessageHasFailed(): void
    {
        $worker = new Worker($this->exceptionHandler, $this->events);
        $worker->shouldQuit = true;

        $failer = m::mock(\RabbitEvents\Foundation\Contracts\Transport::class);
        $worker->setFailer($failer);

        $processor = m::spy(Processor::class);
        $message = m::mock(Message::class);
        $message->shouldReceive('attempts')->andReturn(1);
        $message->shouldReceive('hasFailed')->andReturn(true);

        $consumer = m::mock(Consumer::class);
        $consumer->shouldReceive('nextMessage')->andReturn($message);
        $failer->shouldReceive('send')->with($message)->once();
        $consumer->shouldReceive('acknowledge')->with($message)->once();
        $consumer->shouldNotReceive('reject');

        $status = $worker->work($processor, $consumer, $this->options);

        self::assertEquals(WorkerExitStatus::SUCCESS, $status);
    }
}

class TestWorker extends Worker
{
    public $exitStatus;
    public $resetTimeoutHandler = false;

    public function kill(\RabbitEvents\Listener\WorkerExitStatus|int $status = 0): void
    {
        $this->exitStatus = $status;
    }

    protected function resetTimeoutHandler()
    {
        $this->resetTimeoutHandler = true;
    }
}
