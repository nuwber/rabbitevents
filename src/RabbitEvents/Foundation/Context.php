<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation;

use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpTopic;
use RabbitEvents\Foundation\Amqp\BindFactory;
use RabbitEvents\Foundation\Amqp\DestinationFactory;
use RabbitEvents\Foundation\Amqp\QueueFactory;
use RabbitEvents\Foundation\Contracts\QueueName;
use RabbitEvents\Foundation\Contracts\Transport;
use RabbitEvents\Foundation\Support\Sender;

/**
 * @mixin \Enqueue\AmqpLib\AmqpContext
 */
class Context
{
    /**
     * @var Transport
     */
    private $transport;

    /**
     * @var AmqpTopic
     */
    private $destination;

    /**
     * @param AmqpContext
     */
    private $amqpContext;

    public function __construct(private Connection $connection)
    {
    }

    public function __call(string $method, ?array $args)
    {
        return $this->getAmqpContext()->$method(...$args);
    }

    private function getAmqpContext(): AmqpContext
    {
        if (!$this->amqpContext) {
            $this->amqpContext = $this->connection->createContext();
        }

        return $this->amqpContext;
    }

    public function destination(): AmqpTopic
    {
        if (!$this->destination) {
            $this->destination = (new DestinationFactory($this))->make($this->getExchange());
        }

        return $this->destination;
    }

    public function createConsumer(QueueName $queueName, string $event): Consumer
    {
        $queue = $this->makeQueue($queueName);

        $this->bind($queue, $event);

        return new Consumer($this->getAmqpContext()->createConsumer($queue), $this);
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
        $this->getAmqpContext()->bind(
            (new BindFactory($this))->make($queue, $event)
        );
    }

    public function getTransport(): Transport
    {
        if (!$this->transport) {
            $this->transport = new Sender($this);
        }

        return $this->transport;
    }

    public function setTransport(Transport $transport): self
    {
        $this->transport = $transport;

        return $this;
    }

    protected function getExchange(): string
    {
        return $this->connection->getConfig('exchange');
    }
}
