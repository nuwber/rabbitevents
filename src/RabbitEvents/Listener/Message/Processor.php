<?php

declare(strict_types=1);

namespace RabbitEvents\Listener\Message;

use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use RabbitEvents\Foundation\Message;
use RabbitEvents\Listener\Events\ListenerHandlerExceptionOccurred;
use RabbitEvents\Listener\Events\ListenerHandleFailed;
use RabbitEvents\Listener\Events\ListenerHandled;
use RabbitEvents\Listener\Events\ListenerHandling;
use RabbitEvents\Listener\Exceptions\FailedException;
use RabbitEvents\Listener\Facades\RabbitEvents;
use Throwable;

class Processor
{
    public const HANDLERS_PASSED_PROPERTY = 'handlers-passed';

    public function __construct(private HandlerFactory $handlerFactory, private EventsDispatcher $events)
    {
    }

    /**
     * Fire an event and call the listeners.
     *
     * @throws Throwable
     */
    public function process(Message $message, ProcessingOptions $options): void
    {
        foreach (RabbitEvents::getListeners($message->event()) as $listener) {
            [$class, $callback] = $listener;

            if (!$this->shouldBeHandled($message, $class)) {
                continue;
            }

            $response = $this->runHandler(
                $this->handlerFactory->make($message, $callback, $class),
                $options
            );

            $this->markHandlerAsPassed($message, $class);

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

            $response = $handler->handle();

            $this->raiseAfterEvent($handler);

            return $response;
        } catch (Throwable $e) {
            $this->handleException($handler, $options, $e);
        }
    }

    private function shouldBeHandled(Message $message, string $className): bool
    {
        if (!$handlersPassed = $message->getProperty(self::HANDLERS_PASSED_PROPERTY)) {
            return true;
        }

        return !in_array($className, $handlersPassed, true);
    }

    private function markHandlerAsPassed(Message $message, string $className): void
    {
        $handlersPassed = $message->getProperty(self::HANDLERS_PASSED_PROPERTY, []);

        $handlersPassed[] = $className;

        $message->setProperty(self::HANDLERS_PASSED_PROPERTY, array_unique($handlersPassed, SORT_STRING));
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
            if ($this->couldBeReleased($handler)) {
                $handler->release($options->sleep);
            }
        }

        throw $exception;
    }

    protected function couldBeReleased(Handler $handler)
    {
        return !$handler->isReleased() && !$handler->hasFailed();
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
     */
    protected function raiseBeforeEvent(Handler $handler): void
    {
        $this->events->dispatch(new ListenerHandling($handler));
    }

    /**
     * Raise the after queue job event.
     */
    protected function raiseAfterEvent(Handler $handler): void
    {
        $this->events->dispatch(new ListenerHandled($handler));
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
        $this->events->dispatch(new ListenerHandlerExceptionOccurred($handler, $exception));
    }

    /**
     * Raise the failed queue job event.
     */
    protected function raiseFailedHandleEvent(Handler $handler, Throwable $exception): void
    {
        $this->events->dispatch(new ListenerHandleFailed($handler, $exception));
    }
}
