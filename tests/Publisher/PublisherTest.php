<?php

namespace RabbitEvents\Tests\Publisher;

use Illuminate\Contracts\Support\Arrayable;
use Mockery as m;
use RabbitEvents\Foundation\Contracts\Transport;
use RabbitEvents\Publisher\MessageFactory;
use RabbitEvents\Publisher\Publisher;
use RabbitEvents\Publisher\Support\AbstractPublishableEvent;
use RabbitEvents\Foundation\Message;

class PublisherTest extends TestCase
{
    public function testPublish(): void
    {
        $event = new SomeEvent(new SomeModel(), ['foo' => 'bar'], 'Hello!');

        $messageMock = m::mock(Message::class);
        $messageMock->shouldReceive('id')->andReturn('mock-message-id');

        $messageFactory = m::mock(MessageFactory::class);
        $messageFactory->shouldReceive()
            ->make($event)
            ->andReturn($messageMock);
        $sender = m::mock(Transport::class);
        $sender->shouldReceive()
            ->send($messageMock)
            ->once();

        $publisher = new Publisher($messageFactory, $sender);
        $result = $publisher->publish($event);

        self::assertEquals('mock-message-id', $result);
    }

    public function testPublishAfterCommitDeferred(): void
    {
        $event = new SomeEventAfterCommit(new SomeModel(), ['foo' => 'bar'], 'Hello!');

        $dbMock = m::mock();
        $dbMock->shouldReceive('addCallback')->once(); // Callback deferred until transaction commit

        $messageFactory = m::mock(MessageFactory::class);
        $messageFactory->shouldNotReceive('make');

        $sender = m::mock(Transport::class);
        $sender->shouldNotReceive('send');

        $publisher = (new Publisher($messageFactory, $sender))
            ->setTransactionManagerResolver(fn() => $dbMock);
        $result = $publisher->publish($event);

        self::assertNull($result);
    }

    public function testPublishAfterCommitImmediate(): void
    {
        $event = new SomeEventAfterCommit(new SomeModel(), ['foo' => 'bar'], 'Hello!');

        $dbMock = m::mock();
        $dbMock->shouldReceive('addCallback')->once()->andReturnUsing(function ($callback) {
            $callback();
        });

        $messageMock = m::mock(Message::class);
        $messageMock->shouldReceive('id')->andReturn('mock-message-id');

        $messageFactory = m::mock(MessageFactory::class);
        $messageFactory->shouldReceive()->make($event)->andReturn($messageMock);

        $sender = m::mock(Transport::class);
        $sender->shouldReceive()->send($messageMock)->once();

        $publisher = (new Publisher($messageFactory, $sender))
            ->setTransactionManagerResolver(fn() => $dbMock);
        $result = $publisher->publish($event);

        self::assertEquals('mock-message-id', $result);
    }

    public function testPublishAfterCommitViaProperty(): void
    {
        $event = (new SomeEvent(new SomeModel(), ['foo' => 'bar'], 'Hello!'))->afterCommit();

        $managerMock = m::mock();
        $managerMock->shouldReceive('addCallback')->once(); // Deferred

        $messageFactory = m::mock(MessageFactory::class);
        $messageFactory->shouldNotReceive('make');

        $sender = m::mock(Transport::class);
        $sender->shouldNotReceive('send');

        $publisher = (new Publisher($messageFactory, $sender))
            ->setTransactionManagerResolver(fn() => $managerMock);

        $result = $publisher->publish($event);

        self::assertNull($result);
    }
}

class SomeEventAfterCommit extends SomeEvent implements \RabbitEvents\Publisher\ShouldPublishAfterCommit
{
}

class SomeEvent extends AbstractPublishableEvent
{
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
