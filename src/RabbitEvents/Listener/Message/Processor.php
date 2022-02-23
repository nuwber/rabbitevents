<?php

declare(strict_types=1);

namespace RabbitEvents\Listener\Message;

use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use RabbitEvents\Foundation\Message;
use RabbitEvents\Listener\Events\HandlerExceptionOccurred;
use RabbitEvents\Listener\Events\MessageProcessingFailed;
use RabbitEvents\Listener\Events\MessageProcessed;
use RabbitEvents\Listener\Events\MessageProcessing;
use RabbitEvents\Listener\Exceptions\FailedException;
use RabbitEvents\Listener\Exceptions\MaxAttemptsExceededException;
use RabbitEvents\Listener\Facades\RabbitEvents;
use Throwable;

class Processor
{
    public function __construct(private HandlerFactory $handlerFactory, private EventsDispatcher $events)
    {
    }

    /**
     * Fire an event and call the listeners.
     *
     * @param Message $message
     * @param ProcessingOptions $options
     * @throws Throwable
     */
    public function process(Message $message, ProcessingOptions $options): void
    {
        $message->increaseAttempts();

        foreach (RabbitEvents::getListeners($message->event()) as $listener) {
            [$class, $callback] = $listener;

            $response = $this->runHandler(
                $this->handlerFactory->make($message, $callback, $class),
                $options
            );

            // If a boolean false is returned from a listener, we will stop propagating
            // the event to any further listeners down in the chain, else we keep on
            // looping through the listeners and run every one in our sequence.
            if ($response === false) {
                break;
            }
        }
    }

    /**
     * Process concrete listener
     *
     * @param Handler $handler
     * @param ProcessingOptions $options
     * @return mixed
     * @throws Throwable
     */
    public function runHandler(Handler $handler, ProcessingOptions $options): mixed
    {
        try {
            $this->raiseBeforeEvent($handler);

            $this->markAsFailedIfAlreadyExceedsMaxAttempts($handler, $options);

            $response = $handler->handle();

            $this->raiseAfterEvent($handler);

            return $response;
        } catch (Throwable $e) {
            $this->handleException($handler, $options, $e);
        }
    }

    /**
     * Mark the given job as failed if it has exceeded the maximum allowed attempts.
     *
     * This will likely be because the job previously exceeded a timeout.
     *
     * @param Handler $handler
     * @param ProcessingOptions $options
     * @return void
     */
    protected function markAsFailedIfAlreadyExceedsMaxAttempts(Handler $handler, ProcessingOptions $options): void
    {
        if ($options->maxTries === 0 || $handler->attempts() <= $options->maxTries) {
            return;
        }

        $this->handleFail($handler, $e = new MaxAttemptsExceededException(
            'The Message handle tries has been attempted too many times.'
        ));

        $this->raiseAfterEvent($handler);

        throw $e;
    }

    /**
     * Handle an exception that occurred while the job was running.
     *
     * @param Handler $handler
     * @param ProcessingOptions $options
     * @param Throwable $exception
     * @return void
     *
     * @throws Throwable
     */
    protected function handleException(Handler $handler, ProcessingOptions $options, Throwable $exception): void
    {
        try {
            if ($exception instanceof FailedException) {
                $this->handleFail($handler, $exception);
            }

            // First, we will go ahead and mark the job as failed if it will exceed the maximum
            // attempts it is allowed to run the next time we process it. If so we will just
            // go ahead and mark it as failed now, so we do not have to release this again.
            if (!$handler->hasFailed()) {
                $this->markAsFailedIfWillExceedMaxAttempts($handler, $options->maxTries, $exception);
            }

            $this->raiseExceptionOccurredEvent($handler, $exception);
        } finally {
            // If we catch an exception, we will attempt to release the job back onto the queue
            // so it is not lost entirely. This'll let the job be retried at a later time by
            // another listener (or this same one). We will re-throw this exception after.
            if (!$handler->isReleased() && !$handler->hasFailed()) {
                $handler->release($options->sleep);
            }
        }

        throw $exception;
    }

    /**
     * Mark the given job as failed if it has exceeded the maximum allowed attempts.
     *
     * @param Handler $handler
     * @param int $maxTries
     * @param Throwable $exception
     * @return void
     */
    protected function markAsFailedIfWillExceedMaxAttempts(Handler $handler, int $maxTries, Throwable $exception): void
    {
        if ($maxTries > 0 && $handler->attempts() >= $maxTries) {
            $this->handleFail($handler, $exception);
        }
    }

    protected function handleFail(Handler $handler, Throwable $exception): void
    {
        try {
            $handler->fail($exception);
        } finally {
            $this->raiseFailedHandleEvent($handler, $exception);
        }
    }

    /**
     * Raise the before queue job event.
     *
     * @param Handler $handler
     * @return void
     */
    protected function raiseBeforeEvent(Handler $handler): void
    {
        $this->events->dispatch(new MessageProcessing($handler));
    }

    /**
     * Raise the after queue job event.
     *
     * @param Handler $handler
     * @return void
     */
    protected function raiseAfterEvent(Handler $handler): void
    {
        $this->events->dispatch(new MessageProcessed($handler));
    }

    /**
     * Raise the exception occurred queue job event.
     *
     * @param Handler $handler
     * @param Throwable $exception
     * @return void
     */
    protected function raiseExceptionOccurredEvent(Handler $handler, Throwable $exception): void
    {
        $this->events->dispatch(new HandlerExceptionOccurred($handler, $exception));
    }

    /**
     * Raise the failed queue job event.
     *
     * @param Handler $handler
     * @param Throwable $exception
     * @return void
     */
    protected function raiseFailedHandleEvent(Handler $handler, Throwable $exception): void
    {
        $this->events->dispatch(new MessageProcessingFailed($handler, $exception));
    }
}
