<?php

namespace Nuwber\Events\Tests\Event;

use Illuminate\Contracts\Support\Arrayable;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpProducer;
use Interop\Amqp\AmqpMessage;
use Interop\Amqp\Impl\AmqpTopic;
use InvalidArgumentException;
use Nuwber\Events\Event\Publishable;
use Nuwber\Events\Event\Publisher;
use Nuwber\Events\Event\ShouldPublish;
use Nuwber\Events\Tests\TestCase;

class PublisherTest extends TestCase
{
    private $event = 'something.happened';

    private $payload = ['data' => 'payload'];

    public function testSend()
    {
        $publisher = new Publisher(...$this->makeMocks($this->event, $this->payload));

        $this->assertEquals($publisher, $publisher->send($this->event, $this->payload));
    }

    public function testPublish()
    {
        $payload = [
            (new SomeModel())->toArray(),
            ['foo' => 'bar'],
            'Hello!'
        ];

        $publisher = new Publisher(...$this->makeMocks($this->event, $payload));

        $event = new SomeEvent(new SomeModel(), ['foo' => 'bar'], 'Hello!');

        $this->assertEquals($publisher, $publisher->publish($event));
    }

    public function testPublishWithSeparateEventAndPayload()
    {
        $payload = [
            (new SomeModel())->toArray(),
            ['foo' => 'bar'],
            'Hello!'
        ];

        $publisher = new Publisher(...$this->makeMocks($this->event, $payload));

        $this->assertEquals($publisher, $publisher->publish($this->event, $payload));
    }

    public function testEventClassShouldImplementInterface()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Event must be a string or implement `ShouldPublish` interface');

        $publisher = new Publisher(\Mockery::mock(AmqpContext::class), new AmqpTopic('eventsTesting'));

        $event = new class {};

        $this->assertEquals($publisher, $publisher->publish($event));
    }

    protected function makeMocks(string $event, array $payload)
    {
        $topic = new AmqpTopic('eventsTesting');

        $message = \Mockery::mock(AmqpMessage::class);
        $message->shouldReceive('setRoutingKey')
            ->with($event)
            ->once();

        $producer = \Mockery::mock(AmqpProducer::class);
        $producer->shouldReceive('send')
            ->with($topic, $message)
            ->once();

        $producer->shouldReceive('setDeliveryDelay')
            ->with(0)
            ->once()
            ->andReturnSelf();

        $context = \Mockery::mock(AmqpContext::class);
        $context->shouldReceive('createProducer')
            ->andReturn($producer);

        $context->shouldReceive('createMessage')
            ->with(json_encode($payload, JSON_UNESCAPED_UNICODE))
            ->once()
            ->andReturn($message);

        return [$context, $topic];
    }
}

class SomeEvent implements ShouldPublish
{
    use Publishable;

    /** @var SomeModel */
    private $model;
    /**
     * @var array
     */
    private $array;
    /**
     * @var string
     */
    private $string;

    public function __construct(SomeModel $model, array $array, string $string)
    {
        $this->model = $model;
        $this->array = $array;
        $this->string = $string;
    }

    /**
     * @return array
     */
    public function getArray(): array
    {
        return $this->array;
    }

    /**
     * @return string
     */
    public function getString(): string
    {
        return $this->string;
    }

    public function getModel()
    {
        return $this->model;
    }

    public function publishEventKey(): string
    {
        return 'something.happened';
    }

    public function toPublish(): array
    {
        return [
            $this->model->toArray(),
            $this->array,
            $this->string
        ];
    }
}

class SomeModel implements Arrayable
{

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'item1' => 'value1',
            'item2' => 'value2'
        ];
    }
}
