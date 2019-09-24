<?php

namespace Nuwber\Events\Queue;

use Illuminate\Support\Arr;
use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpMessage;
use Interop\Amqp\AmqpProducer;
use Interop\Queue\Exception\DeliveryDelayNotSupportedException;

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
        AmqpContext $context,
        AmqpConsumer $consumer,
        AmqpMessage $message,
        callable $callback,
        string $listenerClass
    ) {
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
    public function delete()
    {
        parent::delete();
        $this->consumer->acknowledge($this->message);
    }

    /**
     * {@inheritdoc}
     */
    public function release($delay = 0)
    {
        parent::release($delay);

        $requeueMessage = clone $this->message;
        $requeueMessage->setProperty('x-attempts', $this->attempts() + 1);

        /** @var AmqpProducer $producer */
        $producer = $this->context->createProducer();
        try {
            $producer->setDeliveryDelay($this->secondsUntil($delay) * 1000);
        } catch (DeliveryDelayNotSupportedException $e) {
        }

        $this->delete();
        $producer->send($this->consumer->getQueue(), $requeueMessage);
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
}
