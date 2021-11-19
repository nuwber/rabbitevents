<?php

namespace Nuwber\Events\Tests\Queue;

use http\Exception\RuntimeException;
use Interop\Amqp\Impl\AmqpMessage;
use Nuwber\Events\Queue\Manager;
use Nuwber\Events\Queue\Message\Processor;
use Nuwber\Events\Queue\ProcessingOptions;
use Nuwber\Events\Queue\Worker;
use Nuwber\Events\Tests\TestCase;
use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Mockery as m;
use PhpAmqpLib\Exception\AMQPRuntimeException;

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

    protected function tearDown(): void
    {
        Container::setInstance(null);

        parent::tearTown();
    }

    public function testInstantiable()
    {
        self::assertInstanceOf(Worker::class, new Worker($this->exceptionHandler));
    }

    public function testWork()
    {
        $options = $this->options();

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('Stop Worker');

        $worker = new TestWorker($this->exceptionHandler);
        $worker->shouldQuit = true; //For one tick only
        
        $message = new AmqpMessage();
        
        $processor = m::spy(Processor::class);
        
        $queueManager = m::mock(Manager::class)->makePartial();
        $queueManager->shouldReceive()
            ->nextMessage($options->timeout)
            ->andReturn($message);

        $worker->work($processor, $queueManager, $options);

        $processor->shouldHaveReceived()->process($message, $options);
        self::assertEquals(Worker::EXIT_SUCCESS, $worker->stoppedWithStatus);
    }

    public function testStopIfMemoryLimitExceeded()
    {
        self::expectException(\RuntimeException::class);

        $worker = new TestWorker($this->exceptionHandler);

        $queueManager = m::mock(Manager::class)->makePartial();
        $queueManager->shouldReceive('nextMessage')
            ->andReturn(new AmqpMessage());

        $worker->work(m::mock(Processor::class), $queueManager, $this->options(['memory' => 0]));

        self::assertEquals(Worker::EXIT_MEMORY_LIMIT, $worker->stoppedWithStatus);
    }

    public function testStopListeningIfLostConnection()
    {
        $exception = new AMQPRuntimeException;
        self::expectException(\RuntimeException::class);

        $worker = new TestWorker($this->exceptionHandler);

        $queueManager = m::mock(Manager::class);
        $queueManager->shouldReceive('nextMessage')
            ->andThrow($exception);

        $worker->work(m::mock(Processor::class), $queueManager, $this->options());
        $this->exceptionHandler->shouldHaveReceived()->report($exception);

        self::assertEquals(Worker::EXIT_SUCCESS, $worker->stoppedWithStatus);
    }

    public function testFinallyAcknowledge()
    {
        $exception = new \RuntimeException();
        self::expectException(\RuntimeException::class);

        $worker = new TestWorker($this->exceptionHandler);
        $worker->shouldQuit = true; //For one tick only

        $processor = m::mock(Processor::class);
        $processor->shouldReceive('process')
            ->andThrow($exception);

        $message = new AmqpMessage();
        $queueManager = m::mock(Manager::class);
        $queueManager->shouldReceive('nextMessage')
            ->andReturn($message);

        $queueManager->shouldReceive()
            ->acknowledge($message);

        $worker->work($processor, $queueManager, $this->options());

        $this->exceptionHandler->shouldHaveReceived()->report($exception);

        self::assertEquals(Worker::EXIT_SUCCESS, $worker->stoppedWithStatus);
    }

    protected function options(array $overrides = [])
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
    public $stoppedWithStatus;

    protected function stop(int $status = 0): void
    {
        $this->stoppedWithStatus = $status;

        throw new \RuntimeException('Stop Worker');
    }
}
