<?php

namespace RabbitEvents\Tests\Listener\Message;

use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Mockery as m;
use RabbitEvents\Foundation\Contracts\Transport;
use RabbitEvents\Foundation\Message;
use RabbitEvents\Listener\Events\HandlerExceptionOccurred;
use RabbitEvents\Listener\Events\MessageProcessed;
use RabbitEvents\Listener\Events\MessageProcessing;
use RabbitEvents\Listener\Events\MessageProcessingFailed;
use RabbitEvents\Listener\Exceptions\FailedException;
use RabbitEvents\Listener\Exceptions\MaxAttemptsExceededException;
use RabbitEvents\Listener\Facades\RabbitEvents;
use RabbitEvents\Listener\Message\Handler;
use RabbitEvents\Listener\Message\HandlerFactory;
use RabbitEvents\Listener\Message\ProcessingOptions;
use RabbitEvents\Listener\Message\Processor;
use RabbitEvents\Tests\Listener\Payload;
use RabbitEvents\Tests\Listener\TestCase;

class ProcessorTest extends TestCase
{
    private $message;
    private $events;

    public function setUp(): void
    {
        parent::setUp();

        $this->events = m::spy(Dispatcher::class);
        $this->message = new Message('test.event', new Payload(['test' => 'payload']), m::mock(Transport::class));
    }

    public function testProcess()
    {
        $this->mockListeners([
            [\Closure::class, static fn() => true],
            [\Closure::class, static fn() => true]
        ]);

        $processor = new Processor($handlerFactory = new FakeHandlerFactory(), $this->events);

        $this->message->setProperty('x-attempts', 2);

        $processor->process($this->message, $this->options());

        self::assertCount(2, $handlerFactory->handlers);

        foreach ($handlerFactory->handlers as $handler) {
            self::assertTrue($handler->fired);
        }

        $this->events->shouldHaveReceived()
            ->dispatch(m::type(MessageProcessing::class))
            ->twice();

        $this->events->shouldHaveReceived()
            ->dispatch(m::type(MessageProcessed::class))
            ->twice();

        self::assertEquals(3, $this->message->attempts());
    }

    public function testPropagationStopped(): void
    {
        $this->mockListeners([
            [\Closure::class, static fn() => false],
            [\Closure::class, static function () {
                throw new \RuntimeException("This exception shouldn't be thrown because the first listener should stop propagation");
            }]
        ]);

        $handlerFactory = new FakeHandlerFactory();
        $processor = new Processor($handlerFactory, $this->events);

        $processor->process($this->message, $this->options());

        self::assertCount(1, $handlerFactory->handlers);
        self::assertTrue($handlerFactory->handlers[0]->fired);
    }

    public function testProcessFail()
    {
        $this->expectException(FailedException::class);

        $this->mockListeners([
            [\Closure::class, static function () {
                throw new FailedException();
            }]
        ]);

        $handlerFactory = new FakeHandlerFactory();

        $processor = new Processor($handlerFactory, $this->events);

        $processor->process($this->message, $this->options());

        $handler = $handlerFactory->handlers[0];

        self::assertTrue($handler->fired && $handler->failed);
        self::assertFalse($handler->released);

        $this->events->shouldHaveReceived()->dispatch(m::type(MessageProcessing::class))->once();
        $this->events->shouldHaveReceived()->dispatch(m::type(HandlerExceptionOccurred::class))->once();
        $this->events->shouldHaveReceived()->dispatch(m::type(MessageProcessingFailed::class))->once();
        $this->events->shouldNotHaveReceived()->dispatch(m::type(MessageProcessed::class))->once();
    }

    public function testRegularExceptionThenRelease()
    {
        $exceptionMessage = 'Failed handler exception';
        $this->expectExceptionMessage($exceptionMessage);

        $handler = new FakeHandler(
            $this->message,
            function () use ($exceptionMessage) {
                throw new \Exception($exceptionMessage);
            }
        );

        $processor = new Processor(new FakeHandlerFactory(), $this->events);

        $processor->runHandler($handler, $this->options());

        self::assertTrue($handler->fired && $handler->failed && $handler->released);

        $this->events->shouldHaveReceived()->dispatch(m::type(MessageProcessing::class))->once();
        $this->events->shouldHaveReceived()->dispatch(m::type(HandlerExceptionOccurred::class))->once();
        $this->events->shouldHaveReceived()->dispatch(m::type(MessageProcessed::class))->once();
        $this->events->shouldNotHaveReceived()->dispatch(m::type(MessageProcessingFailed::class))->once();
    }

    public function testHandlerIsNotReleasedIfItHasExceededMaxAttempts()
    {
        $this->expectException(MaxAttemptsExceededException::class);
        $handlerFired = false;

        $message = clone $this->message;
        $message->setProperty('x-attempts', 2);
        $handler = new Handler(m::mock(Container::class), $message, static fn() => $handlerFired = true, \Closure::class);

        $processor = new Processor(new FakeHandlerFactory(), $this->events);
        $processor->runHandler($handler, $this->options(['maxTries' => 1]));

        self::assertTrue($handler->hasFailed());
        self::assertFalse($handlerFired);

        $this->events->shouldHaveReceived()->dispatch(m::type(MessageProcessing::class))->once();
        $this->events->shouldHaveReceived()->dispatch(m::type(HandlerExceptionOccurred::class))->once();
        $this->events->shouldHaveReceived()->dispatch(m::type(MessageProcessed::class))->once();
        $this->events->shouldHaveReceived()->dispatch(m::type(MessageProcessingFailed::class))->once();
    }

    public function testDonNotReleaseIfLastAttempt()
    {
        $this->expectException(\RuntimeException::class);

        $message = clone $this->message;
        $message->setProperty('x-attempts', 2);
        $handler = new FakeHandler(
            $this->message,
            function () {throw new \RuntimeException();}
        );

        $processor = new Processor(new FakeHandlerFactory(), $this->events);

        $processor->runHandler($handler, $this->options(['maxTries' => 3]));

        self::assertTrue($handler->hasFailed());
        self::assertFalse($handler->isReleased());

        $this->events->shouldHaveReceived()->dispatch(m::type(MessageProcessing::class))->once();
        $this->events->shouldHaveReceived()->dispatch(m::type(HandlerExceptionOccurred::class))->once();
        $this->events->shouldHaveReceived()->dispatch(m::type(MessageProcessed::class))->once();
        $this->events->shouldHaveReceived()->dispatch(m::type(MessageProcessingFailed::class))->once();
    }

    protected function options(array $overrides = [])
    {
        $options = new ProcessingOptions('test-app', 'rabbitmq');

        foreach ($overrides as $key => $value) {
            $options->{$key} = $value;
        }

        return $options;

    }

    protected function mockListeners(array $listeners)
    {
        RabbitEvents::shouldReceive()
            ->getListeners($this->message->event())
            ->andReturn($listeners);
    }

    /**
     * @after
     */
    protected function clearListenersMock()
    {
        RabbitEvents::clearResolvedInstances();
    }
}

class FakeHandlerFactory extends HandlerFactory
{
    public array $handlers = [];

    public function __construct(public ?Handler $handler = null)
    {
    }

    public function make(Message $message, callable $callback, string $listenerClass): Handler
    {
        if ($this->handler) {
            $this->handlers[] = $this->handler;
            return $this->handler;
        }

        $handler = new FakeHandler($message, $callback, $listenerClass);
        $this->handlers[] = $handler;

        return $handler;
    }
}

class FakeHandler extends Handler
{
    public $message;
    public $listener;
    public bool $fired = false;
    public $callback;
    public $releaseAfter;
    public bool $released = false;
    public $maxTries;
    public $timeoutAt;
    public $attempts = 0;
    public $failedWith;
    public bool $failed = false;
    public $acknowledged = false;

    public function __construct(?Message $message = null, callable $callback = null, string $listener = null)
    {
        $this->message = $message;
        $this->callback = $callback ?: fn() => true;
        $this->listener = $listener;
    }

    public function handle()
    {
        $this->fired = true;
        return call_user_func($this->callback, $this);
    }

    public function getName(): string
    {
        return 'FakeHandler';
    }

    public function payload(): array
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

    public function release($delay = 0): void
    {
        $this->released = true;
        $this->releaseAfter = $delay;
    }

    public function isReleased(): bool
    {
        return $this->released;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function fail($e): void
    {
        $this->markAsFailed();
        $this->failedWith = $e;
    }

    public function __destruct()
    {
        $this->acknowledged = true;
    }
}
