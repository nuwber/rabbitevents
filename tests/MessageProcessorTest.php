<?php

namespace Nuwber\Events\Tests;

use Enqueue\AmqpLib\AmqpConsumer;
use Enqueue\AmqpLib\AmqpProducer;
use Enqueue\AmqpLib\Buffer;
use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Interop\Amqp\Impl\AmqpMessage;
use Interop\Queue\PsrConsumer;
use Interop\Queue\PsrContext;
use Mockery as m;
use Nuwber\Events\Dispatcher;
use Nuwber\Events\Exceptions\FailedException;
use Nuwber\Events\MessageProcessor;
use Nuwber\Events\ProcessingOptions;
use PhpAmqpLib\Channel\AMQPChannel;
use PHPUnit\Framework\Assert;

class MessageProcessorTest extends TestCase
{
    private $payload;
    private $options;
    private $event = 'item.event';
    private $listeners;
    private $broadcastEvents;
    private $data;
    private $context;

    public function setUp()
    {
        $this->data = json_encode(['id' => 1,]);

        $this->payload = new AmqpMessage($this->data);
        $this->payload->setRoutingKey($this->event);

        $producer = m::mock(AmqpProducer::class)->makePartial();

        $this->context = m::mock(PsrContext::class);
        $this->context->shouldReceive('createProducer')
            ->andReturn($producer);

        $this->options = new ProcessingOptions();

        $callback = function ($event, $payload) {
            return "Event: $event, Payload: " . json_encode($payload);
        };

        $this->listeners = [
            'ListenerClassName' => $callback,
            'ListenerClassName1' => $callback,
            'ListenerClassName2' => $callback
        ];

        $this->broadcastEvents = m::mock(Dispatcher::class)->makePartial();
        $this->broadcastEvents->shouldReceive('getListeners')
            ->andReturn($this->listeners);
    }

    public function testProcessJob()
    {
        $result = $this->getProcessor()->process($this->createConsumer(), $this->payload);

        Assert::assertNull($result);
    }

    public function testProcessJobFailException()
    {
        $exception = new FailedException();
        $this->listeners = [
            'ListenerClass' => [function () use ($exception) {
                throw $exception;
            }]
        ];

        $broadcastEvents = m::spy(Dispatcher::class)->makePartial();
        $broadcastEvents->shouldReceive('getListeners')
            ->andReturn($this->listeners);

        $events = m::mock(\Illuminate\Events\Dispatcher::class)->makePartial();
        $events->shouldReceive('dispatch')
            ->with(JobExceptionOccurred::class)
            ->once();

        $exceptionHandler = m::mock(ExceptionHandler::class);
        $exceptionHandler->shouldReceive('report')
            ->with($exception)
            ->once();

        $result = $this->getProcessor($broadcastEvents, $events, $exceptionHandler)
            ->process($this->createConsumer(), $this->payload);

        $this->assertNull($result);
    }

    private function getProcessor($broadcastEvents = null, $events = null, $exceptionHandler = null)
    {
        $broadcastEvents = $broadcastEvents ?: $this->broadcastEvents;

        $events = $events ?: m::mock(\Illuminate\Events\Dispatcher::class)->makePartial();

        $container = m::mock(Container::class)->makePartial();
        $container->shouldReceive('make')
            ->with('Illuminate\Contracts\Events\Dispatcher')
            ->andReturn($events);

        Container::setInstance($container);

        if (!$exceptionHandler) {
            $exceptionHandler = m::mock(ExceptionHandler::class);
            $exceptionHandler->shouldNotReceive();
        }

        return new MessageProcessor(
            $container,
            $this->context,
            $events,
            $broadcastEvents,
            $this->options,
            'interop',
            $exceptionHandler
        );
    }

    /**
     * @return PsrConsumer
     */
    private function createConsumer()
    {
        $queue = new \Interop\Amqp\Impl\AmqpQueue('interop');
        $channel = m::mock(AMQPChannel::class)->makePartial();
        $channel->shouldReceive('basic_ack');

        return new AmqpConsumer($channel, $queue, new Buffer(), 'basic_get');
    }
}
