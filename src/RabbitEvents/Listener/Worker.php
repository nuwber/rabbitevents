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
use RabbitEvents\Listener\Message\ProcessingOptions;
use RabbitEvents\Listener\Message\Processor;
use Throwable;

class Worker
{
    public const EXIT_SUCCESS = 0;
    public const EXIT_ERROR = 1;
    public const EXIT_MEMORY_LIMIT = 12;

    /**
     * Indicates if the worker should exit.
     */
    public bool $shouldQuit = false;

    public function __construct(private ExceptionHandler $exceptions, private EventsDispatcher $events)
    {
    }

    /**
     * @throws Throwable
     */
    public function work(Processor $processor, Consumer $consumer, ProcessingOptions $options): int
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

                $consumer->acknowledge($message);
            }

            $status = $this->stopIfNecessary($options);

            if (!is_null($status)) {
                return $this->stop($status);
            }
        }
    }

    /**
     * @param Consumer $consumer
     * @return Message|void|null
     */
    protected function getNextMessage(Consumer $consumer)
    {
        try {
            return $consumer->nextMessage(1000);
        } catch (Throwable $throwable) {
            $this->exceptions->report($throwable);

            $this->stopListeningIfLostConnection($throwable);
        }
    }

    /**
     * @param Processor $processor
     * @param Message $message
     * @param ProcessingOptions $options
     * @return void
     * @throws Throwable
     */
    private function processMessage(Processor $processor, Message $message, ProcessingOptions $options): void
    {
        try {
            $this->skipIfAlreadyExceedsMaxAttempts($message, $options);

            $processor->process($message, $options);
        } catch (\Throwable $throwable) {
            $this->exceptions->report($throwable);
        }
    }

    /**
     * Register the worker timeout handler.
     */
    protected function registerTimeoutHandler(Message $message, Consumer $consumer, ProcessingOptions $options): void
    {
        // We will register a signal handler for the alarm signal so that we can kill this
        // process if it is running too long because it has frozen. This uses the async
        // signals supported in recent versions of PHP to accomplish it conveniently.
        pcntl_signal(SIGALRM, function () use ($message, $consumer) {
            $this->events->dispatch(new MessageProcessingFailed($message, new TimeoutExceededException(
                'Worker has timed out.'
            )));

            $consumer->acknowledge($message);

            $this->kill(static::EXIT_ERROR);
        });

        pcntl_alarm(max($options->timeout, 0));
    }

    protected function skipIfAlreadyExceedsMaxAttempts(Message $message, ProcessingOptions $options): void
    {
        if ($options->maxTries === 0 || $message->attempts() <= $options->maxTries) {
            return;
        }

        $this->events->dispatch(new MessageProcessingFailed($message, $e = new MaxAttemptsExceededException(
            'The Message handle tries has been attempted too many times.'
        )));

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
    protected function stopIfNecessary(ProcessingOptions $options)
    {
        if ($this->shouldQuit) {
            return self::EXIT_SUCCESS;
        }

        if ($this->memoryExceeded($options->memory)) {
            return self::EXIT_MEMORY_LIMIT;
        }
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
     * @param int $status
     * @return int
     */
    public function stop(int $status = 0)
    {
        $this->events->dispatch(new WorkerStopping($status));

        return $status;
    }

    /**
     * Kill the process.
     *
     * @param int $status
     * @return never
     */
    public function kill($status = 0)
    {
        $this->events->dispatch(new WorkerStopping($status));

        if (extension_loaded('posix')) {
            posix_kill(getmypid(), SIGKILL);
        }

        exit($status);
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
            $this->kill(self::EXIT_SUCCESS);
        });

        pcntl_signal(SIGTERM, function () {
            $this->shouldQuit = true;
        });
    }
}
