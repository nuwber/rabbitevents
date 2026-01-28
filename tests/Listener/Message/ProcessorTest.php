<?php

namespace RabbitEvents\Tests\Listener\Message;
use RabbitEvents\Listener\Dispatcher as RabbitEventsDispatcher;
use Illuminate\Events\Dispatcher;
use Mockery as m;

use RabbitEvents\Foundation\Contracts\Transport;
use RabbitEvents\Foundation\Message;
use RabbitEvents\Listener\Events\ListenerHandled;
use RabbitEvents\Listener\Events\ListenerHandleFailed;
use RabbitEvents\Listener\Events\ListenerHandlerExceptionOccurred;
use RabbitEvents\Listener\Events\ListenerHandling;
use RabbitEvents\Listener\Exceptions\FailedException;

use RabbitEvents\Listener\ListenerOptions;
use RabbitEvents\Listener\Message\Handler;
use RabbitEvents\Listener\Message\HandlerFactory;
use RabbitEvents\Listener\Message\Processor;
use RabbitEvents\Tests\Listener\Payload;
use RabbitEvents\Tests\Listener\TestCase;

class ProcessorTest extends TestCase
{
    private $message;
    private $events;
    private $dispatcher;

    public function setUp(): void
    {
        parent::setUp();

        $this->events = m::spy(Dispatcher::class);
        $this->dispatcher = m::mock(RabbitEventsDispatcher::class);
        $this->message = new Message('test.event', new Payload(['test' => 'payload']));
    }

    public function testProcess()
    {
        $this->mockListeners([
            static fn() => true,
            new FakeHandler($this->message), // Using an invokable object to test class name resolution
        ]);

        $processor = new Processor($handlerFactory = new FakeHandlerFactory(), $this->events, $this->dispatcher);

        $processor->process($this->message, $this->options());

        self::assertCount(2, $handlerFactory->handlers);

        foreach ($handlerFactory->handlers as $handler) {
            self::assertTrue($handler->fired);
        }

        $this->events->shouldHaveReceived()
            ->dispatch(m::type(ListenerHandling::class))
            ->twice();

        $this->events->shouldHaveReceived()
            ->dispatch(m::type(ListenerHandled::class))
            ->twice();
    }

    public function testPropagationStopped(): void
    {
        $this->mockListeners([
            static fn() => false,
            static function () {
                throw new \RuntimeException("This exception shouldn't be thrown because the first listener should stop propagation");
            },
        ]);

        $handlerFactory = new FakeHandlerFactory();
        $processor = new Processor($handlerFactory, $this->events, $this->dispatcher);

        $processor->process($this->message, $this->options());

        self::assertCount(1, $handlerFactory->handlers);
        self::assertTrue($handlerFactory->handlers[0]->fired);
    }

    public function testProcessFail()
    {
        $this->expectException(FailedException::class);

        $this->mockListeners([
            static function () {
                throw new FailedException();
            },
        ]);

        $handlerFactory = new FakeHandlerFactory();

        $processor = new Processor($handlerFactory, $this->events, $this->dispatcher);

        $processor->process($this->message, $this->options());

        $handler = $handlerFactory->handlers[0];

        self::assertTrue($handler->fired && $handler->failed);
        self::assertFalse($handler->released);

        $this->events->shouldHaveReceived()->dispatch(m::type(ListenerHandling::class))->once();
        $this->events->shouldHaveReceived()->dispatch(m::type(ListenerHandlerExceptionOccurred::class))->once();
        $this->events->shouldHaveReceived()->dispatch(m::type(ListenerHandleFailed::class))->once();
        $this->events->shouldNotHaveReceived()->dispatch(m::type(ListenerHandled::class))->once();
    }

    public function testRegularExceptionThenRelease()
    {
        $exceptionMessage = 'Failed handler exception';
        $this->expectExceptionMessage($exceptionMessage);

        $handler = new FakeHandler($this->message);
        $handler->callback = function () use ($exceptionMessage) {
            throw new \Exception($exceptionMessage);
        };

        $processor = new Processor(new FakeHandlerFactory(), $this->events, $this->dispatcher);

        $processor->runHandler($handler, $this->options());

        self::assertTrue($handler->fired && $handler->failed && $handler->released);

        $this->events->shouldHaveReceived()->dispatch(m::type(ListenerHandling::class))->once();
        $this->events->shouldHaveReceived()->dispatch(m::type(ListenerHandlerExceptionOccurred::class))->once();
        $this->events->shouldHaveReceived()->dispatch(m::type(ListenerHandled::class))->once();
        $this->events->shouldNotHaveReceived()->dispatch(m::type(ListenerHandleFailed::class))->once();
    }

    public function testDonNotReleaseIfLastAttempt()
    {
        $this->expectException(\RuntimeException::class);

        $handler = new FakeHandler($this->message);
        $handler->callback = function () {
            throw new \RuntimeException();
        };

        $handler->attempts = 3;

        $processor = new Processor(new FakeHandlerFactory(), $this->events, $this->dispatcher);

        $options = new ListenerOptions(
            'test-app',
            'rabbitmq',
            ['rabbit.event'],
            maxTries: 3
        );

        $processor->runHandler($handler, $options);

        self::assertTrue($handler->hasFailed());
        self::assertFalse($handler->isReleased());

        $this->events->shouldHaveReceived()->dispatch(m::type(ListenerHandling::class))->once();
        $this->events->shouldHaveReceived()->dispatch(m::type(ListenerHandlerExceptionOccurred::class))->once();
        $this->events->shouldHaveReceived()->dispatch(m::type(ListenerHandled::class))->once();
        $this->events->shouldHaveReceived()->dispatch(m::type(ListenerHandleFailed::class))->once();
    }

    protected function options(array $overrides = [])
    {
        $options = new ListenerOptions('test-app', 'rabbitmq', ['event.one', 'event.two']);

        foreach ($overrides as $key => $value) {
            $options->{$key} = $value;
        }

        return $options;
    }

    protected function mockListeners(array $listeners)
    {
        $this->dispatcher->shouldReceive('getListeners')
            ->with($this->message->event)
            ->andReturn($listeners);
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

        $handler = new FakeHandler($message);
        $handler->callback = $callback;
        $handler->setListenerClass($listenerClass);

        $this->handlers[] = $handler;

        return $handler;
    }
}

class FakeHandler extends Handler
{
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
    public $transport;

    public function __construct(Message $message) 
    {
        $this->callback = function(){};
        parent::__construct(
            $message, 
            $this->callback, 
            'FakeListener', 
            m::mock(Transport::class)
        );
    }

    public function handle()
    {
        $this->fired = true;
        return call_user_func($this->callback, $this);
    }

    public function __invoke()
    {
        return $this->handle();
    }

    public function setListenerClass(string $listener)
    {
        $this->listenerClass = $listener;

        return $this;
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
