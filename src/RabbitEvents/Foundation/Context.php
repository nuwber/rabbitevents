<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation;

use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpBind;
use RabbitEvents\Foundation\Amqp\DestinationTopicFactory;
use RabbitEvents\Foundation\Amqp\QueueFactory;
use RabbitEvents\Foundation\Support\EnqueueOptions;

/**
 * @mixin \Enqueue\AmqpLib\AmqpContext
 */
class Context
{
    /**
     * @var AmqpContext
     */
    private AmqpContext $amqpContext;

    public function __construct(public readonly Connection $connection)
    {
        $this->amqpContext = $this->connection->createContext();
    }

    public function __call(string $method, ?array $args)
    {
        return $this->amqpContext->$method(...$args);
    }

    public function makeTopic(): AmqpTopic
    {
        return (new DestinationTopicFactory($this))
            ->makeAndDeclare($this->connection->getConfig('exchange'));
    }

    public function makeConsumer(AmqpQueue $queue): Consumer
    {
        return new Consumer($this->createConsumer($queue));
    }

    public function makeQueue(AmqpTopic $topic, EnqueueOptions $enqueueOptions): AmqpQueue
    {
        $queue = (new QueueFactory($this))->makeAndDeclare($enqueueOptions);

        foreach ($enqueueOptions->events as $event) {
            $this->bind(new AmqpBind($topic, $queue, $event));
        }

        return $queue;
    }
}
