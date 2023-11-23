<?php

namespace RabbitEvents\Tests\Listener;

use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Mockery as m;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use RabbitEvents\Foundation\Consumer;
use RabbitEvents\Foundation\Exceptions\ConnectionLostException;
use RabbitEvents\Foundation\Message;
use RabbitEvents\Listener\Dispatcher;
use RabbitEvents\Listener\Events\ListenerHandled;
use RabbitEvents\Listener\Events\ListenerHandleFailed;
use RabbitEvents\Listener\Events\ListenerHandlerExceptionOccurred;
use RabbitEvents\Listener\Events\ListenerHandling;
use RabbitEvents\Listener\Events\MessageProcessingFailed;
use RabbitEvents\Listener\Events\WorkerStopping;
use RabbitEvents\Listener\Exceptions\MaxAttemptsExceededException;
use RabbitEvents\Listener\Message\ProcessingOptions;
use RabbitEvents\Listener\Message\Processor;
use RabbitEvents\Listener\Worker;
use RabbitEvents\Tests\Listener\Message\FakeHandlerFactory;

class WorkerTest extends TestCase
{
    public $events;
    public $exceptionHandler;

    protected function setUp(): void
    {
        $this->events = m::spy(Dispatcher::class);
        $this->exceptionHandler = m::spy(ExceptionHandler::class);

        Container::setInstance($container = new Container);

        $container->instance(ExceptionHandler::class, $this->exceptionHandler);
    }

    /**
     * @after
     */
    protected function tearDown(): void
    {
        Container::setInstance(null);
    }

    public function testInstantiable(): void
    {
        self::assertInstanceOf(Worker::class, new Worker($this->exceptionHandler, $this->events));
    }

    public function testWork(): void
    {
        $options = $this->options();

        $worker = new Worker($this->exceptionHandler, $this->events);
        $worker->shouldQuit = true; //For one tick only
        
        $processor = m::spy(Processor::class);
        $message = m::mock(Message::class);
        $consumer = m::mock(Consumer::class)->makePartial();
        $consumer->shouldReceive('nextMessage')
            ->andReturn($message);
        $consumer->shouldReceive('acknowledge')->once();

        $status = $worker->work($processor, $consumer, $options);

        self::assertEquals(Worker::EXIT_SUCCESS, $status);

        $processor->shouldHaveReceived()->process($message, $options);
        $this->events->shouldHaveReceived()->dispatch(m::type(WorkerStopping::class))->once();
    }

    public function testStopIfMemoryLimitExceeded(): void
    {
        $worker = new Worker($this->exceptionHandler, $this->events);

        $consumer = m::mock(Consumer::class);
        $consumer->shouldReceive('nextMessage')
            ->andReturn(m::mock(Message::class));
        $consumer->shouldReceive('acknowledge')->once();

        $status = $worker->work(m::spy(Processor::class), $consumer, $this->options(['memory' => 0]));

        self::assertEquals(Worker::EXIT_MEMORY_LIMIT, $status);
        $this->events->shouldHaveReceived()->dispatch(m::type(WorkerStopping::class))->once();
    }

    public function testStopListeningIfLostConnection(): void
    {
        $exception = new ConnectionLostException();

        $worker = new Worker($this->exceptionHandler, $this->events);

        $consumer = m::mock(Consumer::class);
        $consumer->shouldReceive('nextMessage')
            ->andThrow($exception);

        $status = $worker->work(m::mock(Processor::class), $consumer, $this->options());

        self::assertEquals(Worker::EXIT_SUCCESS, $status);

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

        $message = m::mock(Message::class);
        $consumer = m::mock(Consumer::class);
        $consumer->shouldReceive('nextMessage')
            ->andReturn($message);

        $consumer->shouldReceive()
            ->acknowledge($message);

        $status = $worker->work($processor, $consumer, $this->options());

        self::assertEquals(Worker::EXIT_SUCCESS, $status);

        $this->exceptionHandler->shouldHaveReceived()->report($exception);
        $this->events->shouldHaveReceived()->dispatch(m::type(WorkerStopping::class))->once();
    }

    public function testProcessNotStartedIfExceededMaxAttempts()
    {
        $message = m::mock(Message::class);
        $message->shouldReceive('attempts')
            ->andReturn(3);

        $consumer = m::mock(Consumer::class);
        $consumer->shouldReceive()
            ->nextMessage(1000)
            ->andReturn($message);
        $consumer->shouldReceive('acknowledge')->once();

        $processor = m::spy(Processor::class);

        $worker = new Worker($this->exceptionHandler, $this->events);
        $worker->shouldQuit = true; //one tick

        $worker->work($processor, $consumer, $this->options(['maxTries' => 2]));

        $processor->shouldNotHaveReceived('process');

        $this->events->shouldHaveReceived()->dispatch(m::type(MessageProcessingFailed::class))->once();
    }

    public function testExitIfTimedOut()
    {
        $consumer = m::mock(Consumer::class);
        $consumer->shouldReceive('nextMessage')
            ->andReturn(m::mock(Message::class));

        $consumer->shouldReceive('acknowledge')->twice(); //The second is because of $shouldQuite === true

        $processor = m::mock(Processor::class);
        $processor->shouldReceive('process')
            ->andReturnUsing(function () {
                sleep(2);

                return true;
            });

        $worker = new TestWorker($this->exceptionHandler, $this->events);
        $worker->shouldQuit = true;
        $worker->work($processor, $consumer, $this->options(['timeout' => 1]));

        self::assertEquals(Worker::EXIT_ERROR, $worker->exitStatus);

        $this->events->shouldHaveReceived()->dispatch(m::type(MessageProcessingFailed::class))->once();
    }

    public function testTimeoutResetIfProcessEndedWithException()
    {
        $consumer = m::mock(Consumer::class);
        $consumer->shouldReceive('nextMessage')
            ->andReturn(m::mock(Message::class));

        $consumer->shouldReceive('acknowledge')->once();

        $processor = m::mock(Processor::class);
        $processor->shouldReceive('process')
            ->andThrow(new \RuntimeException('Stopped unexpectedly'));

        $worker = new TestWorker($this->exceptionHandler, $this->events);
        $worker->shouldQuit = true;
        $worker->work($processor, $consumer, $this->options(['timeout' => 1]));

        self::assertTrue($worker->resetTimeoutHandler);
    }

    protected function options(array $overrides = []): ProcessingOptions
    {
        $options = new ProcessingOptions('test-app', 'rabbitmq');

        foreach ($overrides as $key => $value) {
            $options->{$key} = $value;
        }

        return $options;
    }
}


class TestWorker extends Worker
{
    public $exitStatus;
    public $resetTimeoutHandler = false;

    public function kill($status = 0)
    {
        $this->exitStatus = $status;
    }

    protected function resetTimeoutHandler()
    {
        $this->resetTimeoutHandler = true;
    }
}
