<?php

namespace Nuwber\Events;

use Illuminate\Container\Container;
use Illuminate\Support\Arr;
use Interop\Amqp\AmqpMessage;
use Interop\Queue\PsrConsumer;
use Interop\Queue\PsrContext;

class Job extends \Enqueue\LaravelQueue\Job
{
    /**
     * @var \Callback
     */
    private $listener;
    /**
     * @var PsrConsumer
     */
    private $event;

    /** @var string */
    protected $name;
    /**
     * @var string
     */
    private $listenerClass;

    public function __construct(
        Container $app,
        PsrContext $context,
        PsrConsumer $consumer,
        AmqpMessage $message,
        $connectionName,
        callable $callback,
        string $listenerClass
    ) {
        parent::__construct($app, $context, $consumer, $message, $connectionName);

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
        return sprintf('%s:%s:%s', $this->connectionName, $this->event, $this->listenerClass);
    }

    public function failed($exception)
    {
        $this->markAsFailed();

        if (method_exists($this->instance = $this->resolve($this->listenerClass), 'failed')) {
            $this->instance->failed($this->payload(), $exception);
        }
    }
}
