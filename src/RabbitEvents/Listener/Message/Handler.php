<?php

declare(strict_types=1);

namespace RabbitEvents\Listener\Message;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Arr;
use RabbitEvents\Foundation\Message;
use Throwable;

class Handler
{
    /**
     * @var callable
     */
    private $listener;

    /**
     * Indicates if the handler has been released.
     *
     * @var bool
     */
    protected bool $released = false;

    /**
     * Indicates if the handle attempt has failed.
     *
     * @var bool
     */
    protected bool $failed = false;

    public function __construct(
        protected Container $container,
        private Message $message,
        callable $listener,
        private string $listenerClass
    ) {
        $this->listener = $listener;
    }

    public function handle()
    {
        return call_user_func($this->listener, $this->message->event(), Arr::wrap($this->payload()));
    }

    public function payload(): mixed
    {
        return json_decode($this->message->getBody(), true);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->message->event() . ':' . $this->listenerClass;
    }

    public function getMessage(): Message
    {
        return $this->message;
    }

    /**
     * Delete the message, call the "failed" method, and raise the failed handler event.
     *
     * @param Throwable $exception
     * @return void
     */
    public function fail(Throwable $exception): void
    {
        $this->markAsFailed();

        // If the handling attempt has failed, call the listener's "failed" method. This is
        // to allow every developer to better keep monitor of their failed handling attempts.
        if (
            $this->listenerClass !== \Closure::class
            && method_exists($listener = $this->container->make($this->listenerClass), 'failed')
        ) {
            $listener->failed($this->payload(), $exception);
        }
    }

    /**
     * Determine if the handler has been marked as a failure.
     */
    public function hasFailed(): bool
    {
        return $this->failed;
    }

    /**
     * Mark the handler as "failed".
     */
    public function markAsFailed(): void
    {
        $this->failed = true;
    }

    /**
     * Release the message back into the queue.
     *
     * @param int $delay
     */
    public function release(int $delay = 0): void
    {
        $this->message->release($delay);

        $this->released = true;
    }

    /**
     * Determine if the message was released back into the queue.
     */
    public function isReleased(): bool
    {
        return $this->released;
    }

    /**
     * Returns the number of attempts to handle the message
     *
     * @return int
     */
    public function attempts(): int
    {
        return $this->message->attempts();
    }
}
