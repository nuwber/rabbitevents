<?php

namespace Nuwber\Events\Queue\Jobs;

use Illuminate\Support\Arr;
use Interop\Amqp\AmqpMessage;
use Nuwber\Events\Queue\Manager;
use Illuminate\Container\Container;

class Job extends \Illuminate\Queue\Jobs\Job implements \Illuminate\Contracts\Queue\Job
{
    /**
     * @var Manager
     */
    private $queueManager;

    /**
     * @var AmqpMessage
     */
    private $message;

    /**
     * @var Callback
     */
    protected $listener;

    /**
     * @var string
     */
    private $listenerClass;

    /**
     * @var ?string
     */
    private $event;


    public function __construct(
        Container $container,
        Manager $queueManager,
        AmqpMessage $message,
        callable $callback,
        string $listenerClass
    ) {
        $this->container = $container;
        $this->queueManager = $queueManager;
        $this->message = $message;
        $this->listener = $callback;
        $this->listenerClass = $listenerClass;

        $this->event = $message->getRoutingKey();
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
    public function getName(): string
    {
        return sprintf('%s:%s', $this->getQueue(), $this->listenerClass);
    }

    public function failed($e): void
    {
        $this->markAsFailed();

        if (
            $this->listenerClass !== \Closure::class
            && method_exists($listener = $this->resolve($this->listenerClass), 'failed')
        ) {
            $listener->failed($this->payload(), $e);
        }
    }

    public function getJobId(): ?string
    {
        return $this->message->getMessageId();
    }

    /**
     * @inheritdoc
     */
    public function release($delay = 0): void
    {
        parent::release();

        $this->queueManager->release($this->message, $this->attempts(), $delay);
    }

    public function attempts(): int
    {
        return $this->message->getProperty('x-attempts', 1);
    }

    /**
     * Get the raw body of the job.
     */
    public function getRawBody(): string
    {
        return $this->message->getBody();
    }

    public function getQueue(): string
    {
        return $this->queueManager->getEvent();
    }

    public function displayName(): string
    {
        return $this->getName();
    }

    /**
     * Get the timestamp indicating when the job should timeout.
     */
    public function timeoutAt(): ?int
    {
        return $this->payload()['timeoutAt'] ?? null;
    }
}
