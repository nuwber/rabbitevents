<?php

declare(strict_types=1);

namespace RabbitEvents\Listener;

use Illuminate\Contracts\Debug\ExceptionHandler;
use RabbitEvents\Foundation\Consumer;
use RabbitEvents\Foundation\Exceptions\ConnectionLostException;
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
     *
     * @var bool
     */
    public $shouldQuit;

    public function __construct(private ExceptionHandler $exceptions)
    {
    }

    public function work(Processor $processor, Consumer $consumer, ProcessingOptions $options): int
    {
        if ($supportsAsyncSignals = $this->supportsAsyncSignals()) {
            $this->listenForSignals();
        }

        while (true) {
            try {
                if ($message = $consumer->nextMessage(1000)) {
                    if ($supportsAsyncSignals) {
                        $this->registerTimeoutHandler($options);
                    }

                    try {
                        $processor->process($message, $options);
                    } finally {
                        $consumer->acknowledge($message);
                    }

                    if ($supportsAsyncSignals) {
                        $this->resetTimeoutHandler();
                    }
                }
            } catch (Throwable $throwable) {
                $this->exceptions->report($throwable);

                $this->stopListeningIfLostConnection($throwable);
            }

            $status = $this->stopIfNecessary($options);

            if (! is_null($status)) {
                return $status;
            }
        }
    }

    /**
     * Register the worker timeout handler.
     */
    protected function registerTimeoutHandler(ProcessingOptions $options)
    {
        // We will register a signal handler for the alarm signal so that we can kill this
        // process if it is running too long because it has frozen. This uses the async
        // signals supported in recent versions of PHP to accomplish it conveniently.
        pcntl_signal(SIGALRM, fn() => $this->kill(static::EXIT_ERROR));

        pcntl_alarm(max($options->timeout, 0));
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
     * Kill the process.
     *
     * @param  int  $status
     * @return never
     */
    public function kill($status = 0)
    {
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

        foreach ([SIGINT, SIGTERM] as $signal) {
            pcntl_signal($signal, function () {
                $this->shouldQuit = true;
            });
        }
    }
}
