<?php

namespace Nuwber\Events;

use Illuminate\Container\Container;
use Illuminate\Support\Arr;
use Interop\Queue\PsrConsumer;
use Interop\Queue\PsrContext;
use Interop\Queue\PsrMessage;

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
    /**
     * @var
     */
    private $listenerName;

    public function __construct(
        Container $container,
        PsrContext $context,
        PsrConsumer $consumer,
        PsrMessage $message,
        string $connectionName,
        string $event,
        $listenerName,
        callable $callback
    ) {
        parent::__construct($container, $context, $consumer, $message, $connectionName);

        $this->connectionName = $connectionName;
        $this->event = $event;
        $this->listenerName = $listenerName;
        $this->listener = $callback;
    }

    public function fire()
    {
        $callback = $this->listener();

        return $callback($this->event, Arr::wrap($this->payload()));
    }

    public function listener()
    {
        return $this->listener;
    }

    public function getName()
    {
        return "$this->connectionName: " . $this->event . ":$this->listenerName";
    }

    public function failed($exception)
    {
        //TODO To think how can we use this method
    }
}
