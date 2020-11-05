<?php

namespace Nuwber\Events\Queue;

use Illuminate\Container\Container;
use Illuminate\Support\Arr;
use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpMessage;
use Interop\Amqp\AmqpProducer;
use Interop\Amqp\AmqpTopic;
use Interop\Queue\Exception\DeliveryDelayNotSupportedException;
use Nuwber\Events\Event\Publisher;

class Job extends \Illuminate\Queue\Jobs\Job
{
    /**
     * @var Callback
     */
    private $listener;

    /** @var string */
    protected $name;
    /**
     * @var string
     */
    private $listenerClass;
    /**
     * @var AmqpMessage
     */
    private $message;
    /**
     * @var AmqpConsumer
     */
    private $consumer;
    /**
     * @var AmqpContext
     */
    private $context;
    /**
     * @var string
     */
    private $event;

    public function __construct(
        Container $container,
        AmqpContext $context,
        AmqpConsumer $consumer,
        AmqpMessage $message,
        callable $callback,
        string $listenerClass
    ) {
        $this->container = $container;
        $this->context = $context;
        $this->consumer = $consumer;
        $this->message = $message;
        $this->event = $message->getRoutingKey();
        $this->listener = $callback;
        $this->listenerClass = $listenerClass;
    }

    /**
     * @inheritdoc
     */
    public function fire()
    {
        return call_user_func($this->listener, $this->event, Arr::wrap($this->payload()));
    }

    /**
     * @return string
     */
    public function getName()
    {
        return sprintf('%s:%s', $this->event, $this->listenerClass);
    }

    public function failed($exception)
    {
        $this->markAsFailed();

        if (method_exists($this->instance = $this->resolve($this->listenerClass), 'failed')) {
            $this->instance->failed($this->payload(), $exception);
        }
    }

    public function getJobId()
    {
        return $this->message->getMessageId();
    }

    /**
     * {@inheritdoc}
     */
    public function release($delay = 0)
    {
        parent::release();

        $requeueMessage = clone $this->message;
        $requeueMessage->setProperty('x-attempts', $this->attempts() + 1);

        $this->container->make(Publisher::class)->sendMessage($requeueMessage, $delay);
    }

    public function attempts()
    {
        return $this->message->getProperty('x-attempts', 1);
    }

    /**
     * Get the raw body of the job.
     *
     * @return string
     */
    public function getRawBody()
    {
        return $this->message->getBody();
    }

    public function getQueue()
    {
        return $this->consumer->getQueue()->getQueueName();
    }

    public function displayName()
    {
        return $this->getName();
    }
    
    /**
     * Get the timestamp indicating when the job should timeout.
     *
     * @return int|null
     */
    public function timeoutAt()
    {
        return $this->payload()['timeoutAt'] ?? null;
    }
}
