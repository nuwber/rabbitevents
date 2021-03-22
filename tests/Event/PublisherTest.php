<?php

namespace Nuwber\Events\Tests\Event;

use Enqueue\AmqpLib\AmqpContext;
use Illuminate\Contracts\Support\Arrayable;
use Interop\Queue\Topic;
use Mockery as m;
use Nuwber\Events\Event\Publishable;
use Nuwber\Events\Event\Publisher;
use Nuwber\Events\Event\ShouldPublish;
use Nuwber\Events\Queue\Message\Sender;
use Nuwber\Events\Queue\Context;
use Nuwber\Events\Tests\TestCase;

class PublisherTest extends TestCase
{
    public function testPublish()
    {
        $sender = m::spy(Sender::class);

        $context = new Context(m::mock(AmqpContext::class), m::mock(Topic::class));
        $context->setTransport($sender);

        $publisher = new Publisher($context);
        self::assertNull($publisher->publish($this->getEvent()));

        $sender->shouldHaveReceived('send');
    }

    protected function getEvent()
    {
        return new SomeEvent(new SomeModel(), ['foo' => 'bar'], 'Hello!');
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
