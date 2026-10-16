<?php

declare(strict_types=1);

namespace RabbitEvents\Listener;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use RabbitEvents\Foundation\Consumer;
use RabbitEvents\Foundation\Exceptions\ConnectionLostException;
use RabbitEvents\Foundation\Message;
use RabbitEvents\Listener\Events\MessageProcessingFailed;
use RabbitEvents\Listener\Events\WorkerStopping;
use RabbitEvents\Listener\Exceptions\MaxAttemptsExceededException;
use RabbitEvents\Listener\Exceptions\TimeoutExceededException;
use RabbitEvents\Listener\Message\Processor;
use Throwable;

class Worker
{
    /**
     * Indicates if the worker should exit.
     */
    public bool $shouldQuit = false;

    protected ?\RabbitEvents\Foundation\Contracts\Transport $failer = null;

    public function __construct(
        private ExceptionHandler $exceptions,
        private EventsDispatcher $events
    ) {
    }

    public function setFailer(?\RabbitEvents\Foundation\Contracts\Transport $failer): self
    {
        $this->failer = $failer;

        return $this;
    }

    /**
     * @throws Throwable
     */
    public function work(Processor $processor, Consumer $consumer, ListenerOptions $options): WorkerExitStatus
    {
        if ($supportsAsyncSignals = $this->supportsAsyncSignals()) {
            $this->listenForSignals();
        }

        while (true) {
            if ($message = $this->getNextMessage($consumer)) {
                if ($supportsAsyncSignals) {
                    $this->registerTimeoutHandler($message, $consumer, $options);
                }

                $this->processMessage($processor, $message, $options);

                if ($supportsAsyncSignals) {
                    $this->resetTimeoutHandler();
                }

                if ($message->hasFailed()) {
                    $this->failMessage($message, $consumer);
                } else {
                    $consumer->acknowledge($message);
                }
            }

            $status = $this->stopIfNecessary($options);

            if (!is_null($status)) {
                return $this->stop($status);
            }
        }
    }

    /**
     * @param Consumer $consumer
     * @return Message|null
     */
    protected function getNextMessage(Consumer $consumer): ?Message
    {
        try {
            return $consumer->nextMessage(1000);
        } catch (Throwable $throwable) {
            $this->exceptions->report($throwable);

            $this->stopListeningIfLostConnection($throwable);
        }

        return null;
    }

    /**
     * @param Processor $processor
     * @param Message $message
     * @param ListenerOptions $options
     * @return void
     */
    private function processMessage(Processor $processor, Message $message, ListenerOptions $options): void
    {
        try {
            $this->skipIfAlreadyExceedsMaxAttempts($message, $options);

            $processor->process($message, $options);
        } catch (Throwable $throwable) {
            $this->exceptions->report($throwable);
        }
    }

    /**
     * Register the worker timeout handler.
     */
    protected function registerTimeoutHandler(Message $message, Consumer $consumer, ListenerOptions $options): void
    {
        // We will register a signal handler for the alarm signal so that we can kill this
        // process if it is running too long because it has frozen. This uses the async
        // signals supported in recent versions of PHP to accomplish it conveniently.
        pcntl_signal(SIGALRM, function () use ($message, $consumer) {
            $e = new TimeoutExceededException('Worker has timed out.');
            $this->events->dispatch(new MessageProcessingFailed($message, $e));

            $message->markAsFailed($e);
            $this->failMessage($message, $consumer);

            $this->kill(WorkerExitStatus::ERROR);
        });

        pcntl_alarm(max($options->timeout, 0));
    }

    protected function failMessage(Message $message, Consumer $consumer): void
    {
        if ($this->failer) {
            $this->failer->send($message);
            $consumer->acknowledge($message);
        } else {
            $consumer->reject($message, false);
        }
    }

    protected function skipIfAlreadyExceedsMaxAttempts(Message $message, ListenerOptions $options): void
    {
        if ($options->maxTries === 0 || $message->attempts() <= $options->maxTries) {
            return;
        }

        $e = new MaxAttemptsExceededException(
            'The Message handle tries has been attempted too many times.'
        );
        $message->markAsFailed($e);

        $this->events->dispatch(new MessageProcessingFailed($message, $e));

        throw $e;
    }

    /**
     * Reset the worker timeout handler.
     */
    protected function resetTimeoutHandler()
    {
        pcntl_alarm(0);
    }

    /**
     * @param $throwable
     */
    protected function stopListeningIfLostConnection($throwable): void
    {
        if ($throwable instanceof ConnectionLostException) {
            $this->shouldQuit = true;
        }
    }

    /**
     * Stop the process if necessary.
     *
     * @return null|int
     */
    protected function stopIfNecessary(ListenerOptions $options): ?WorkerExitStatus
    {
        if ($this->shouldQuit) {
            return WorkerExitStatus::SUCCESS;
        }

        if ($this->memoryExceeded($options->memory)) {
            return WorkerExitStatus::MEMORY_LIMIT;
        }

        return null;
    }

    /**
     * Determine if the memory limit has been exceeded.
     *
     * @param int $memoryLimit
     * @return bool
     */
    protected function memoryExceeded(int $memoryLimit): bool
    {
        return (memory_get_usage(true) / 1024 / 1024) >= $memoryLimit;
    }

    /**
     * Determine if "async" signals are supported.
     *
     * @return bool
     */
    protected function supportsAsyncSignals(): bool
    {
        return extension_loaded('pcntl');
    }

    /**
     * Stop listening and bail out of the script.
     *
     * @param WorkerExitStatus $status
     * @return WorkerExitStatus
     */
    public function stop(WorkerExitStatus $status): WorkerExitStatus
    {
        $this->events->dispatch(new WorkerStopping($status->value));

        return $status;
    }

    /**
     * Kill the process.
     *
     * @param int|WorkerExitStatus $status
     * @return never
     */
    public function kill(int|WorkerExitStatus $status = 0): void
    {
        $exitCode = $status instanceof WorkerExitStatus ? $status->value : $status;

        $this->events->dispatch(new WorkerStopping($exitCode));

        if (extension_loaded('posix')) {
            posix_kill(getmypid(), SIGKILL);
        }

        exit($exitCode);
    }

    /**
     * Enable async signals for the process.
     *
     * @return void
     */
    protected function listenForSignals(): void
    {
        pcntl_async_signals(true);

        pcntl_signal(SIGINT, function () {
            $this->kill(WorkerExitStatus::SUCCESS);
        });

        pcntl_signal(SIGTERM, function () {
            $this->shouldQuit = true;
        });
    }
}
