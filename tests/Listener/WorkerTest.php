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
use RabbitEvents\Listener\Message\ProcessingOptions;
use RabbitEvents\Listener\Message\Processor;
use RabbitEvents\Listener\Worker;

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
        self::assertInstanceOf(Worker::class, new Worker($this->exceptionHandler));
    }

    public function testWork(): void
    {
        $options = $this->options();

        $worker = new TestWorker($this->exceptionHandler);
        $worker->shouldQuit = true; //For one tick only
        
        $processor = m::spy(Processor::class);
        $message = m::mock(Message::class);
        $consumer = m::mock(Consumer::class)->makePartial();
        $consumer->shouldReceive('nextMessage')
            ->andReturn($message);
        $consumer->shouldReceive('acknowledge')->once();

        $status = $worker->work($processor, $consumer, $options);

        $processor->shouldHaveReceived()->process($message, $options);
        self::assertEquals(Worker::EXIT_SUCCESS, $status);
    }

    public function testStopIfMemoryLimitExceeded(): void
    {
        $worker = new TestWorker($this->exceptionHandler);

        $consumer = m::mock(Consumer::class)->makePartial();
        $consumer->shouldReceive('nextMessage')
            ->andReturn(m::mock(Message::class));

        $status = $worker->work(m::mock(Processor::class), $consumer, $this->options(['memory' => 0]));

        self::assertEquals(Worker::EXIT_MEMORY_LIMIT, $status);
    }

    public function testStopListeningIfLostConnection(): void
    {
        $exception = new ConnectionLostException();

        $worker = new TestWorker($this->exceptionHandler);

        $consumer = m::mock(Consumer::class);
        $consumer->shouldReceive('nextMessage')
            ->andThrow($exception);

        $status = $worker->work(m::mock(Processor::class), $consumer, $this->options());
        $this->exceptionHandler->shouldHaveReceived()->report($exception);

        self::assertEquals(Worker::EXIT_SUCCESS, $status);
    }

    public function testFinallyAcknowledge(): void
    {
        $exception = new \RuntimeException();

        $worker = new TestWorker($this->exceptionHandler);
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

        $this->exceptionHandler->shouldHaveReceived()->report($exception);

        self::assertEquals(Worker::EXIT_SUCCESS, $status);
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
    public $stoppedWithStatus;

    protected function stop(int $status = 0): int
    {
        return 0;
    }
}
