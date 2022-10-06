<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation;

use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpTopic;
use RabbitEvents\Foundation\Amqp\BindFactory;
use RabbitEvents\Foundation\Amqp\TopicDestinationFactory;
use RabbitEvents\Foundation\Amqp\QueueFactory;
use RabbitEvents\Foundation\Contracts\QueueName;
use RabbitEvents\Foundation\Contracts\Transport;

/**
 * @mixin \Enqueue\AmqpLib\AmqpContext
 */
class Context
{
    /**
     * @var Transport
     */
    private $sender;

    /**
     * @var AmqpTopic
     */
    private $topic;

    /**
     * @param AmqpContext
     */
    private $amqpContext;

    public function __construct(private Connection $connection)
    {
    }

    public function __call(string $method, ?array $args)
    {
        return $this->amqpContext()->$method(...$args);
    }

    private function amqpContext(): AmqpContext
    {
        if (!$this->amqpContext) {
            $this->amqpContext = $this->connection->createContext();
        }

        return $this->amqpContext;
    }

    public function topic(): AmqpTopic
    {
        if (!$this->topic) {
            $this->topic = (new TopicDestinationFactory($this))->make();
        }

        return $this->topic;
    }

    public function makeConsumer(AmqpQueue $queue, string $event): Consumer
    {
        $this->bind($queue, $event);

        return new Consumer($this->createConsumer($queue));
    }

    public function makeQueue(QueueName $queueName): AmqpQueue
    {
        return (new QueueFactory($this))->make($queueName);
    }

    /**
     * @param AmqpQueue $queue
     * @param string $event
     * @return void
     */
    private function bind(AmqpQueue $queue, string $event): void
    {
        $this->amqpContext()->bind(
            (new BindFactory())->make($this->topic(), $queue, $event)
        );
    }

    public function connection(): Connection
    {
        return $this->connection;
    }
}
