<?php

namespace Nuwber\Events\Queue;

use Nuwber\Events\Queue\Message\Processor;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Throwable;

class Worker
{
    public const EXIT_SUCCESS = 0;
    public const EXIT_ERROR = 1;
    public const EXIT_MEMORY_LIMIT = 12;

    /**
     * Indicates if the listener should exit.
     */
    public $shouldQuit;

    /**
     * @var ExceptionHandler
     */
    private $exceptions;

    public function __construct(ExceptionHandler $exceptions)
    {
        $this->exceptions = $exceptions;
    }

    /**
     * @param Processor $processor
     * @param Manager $queue
     * @param ProcessingOptions $options
     * @throws Throwable
     */
    public function work(Processor $processor, Manager $queue, ProcessingOptions $options): void
    {
        if ($this->supportsAsyncSignals()) {
            $this->listenForSignals();
        }

        while (true) {
            try {
                if ($message = $queue->nextMessage($options->timeout)) {
                    $processor->process($message, $options);
                }
            } catch (Throwable $throwable) {
                $this->exceptions->report($throwable);

                $this->stopListeningIfLostConnection($throwable);
            }
            $this->stopIfNecessary($options);
        }
    }

    /**
     * @param $throwable
     */
    protected function stopListeningIfLostConnection($throwable): void
    {
        if ($throwable instanceof AMQPRuntimeException) {
            $this->shouldQuit = true;
        }
    }

    /**
     * Stop the process if necessary.
     * @param ProcessingOptions $options
     */
    protected function stopIfNecessary(ProcessingOptions $options): void
    {
        if ($this->shouldQuit) {
            $this->stop(self::EXIT_SUCCESS);
        }

        if ($this->memoryExceeded($options->memory)) {
            $this->stop(self::EXIT_MEMORY_LIMIT);
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
     * Stop listening and bail out of the script.
     *
     * @param int $status
     * @return void
     */
    protected function stop(int $status = 0): void
    {
        exit($status);
    }

    /**
     * Kill the process.
     *
     * @param int $status
     * @return void
     */
    public function kill(int $status = 0)
    {
        if (extension_loaded('posix')) {
            posix_kill(getmypid(), SIGKILL);
        }

        $this->stop($status);
    }

    /**
     * Determine if "async" signals are supported.
     *
     * @return bool
     */
    protected function supportsAsyncSignals()
    {
        return extension_loaded('pcntl');
    }

    /**
     * Enable async signals for the process.
     *
     * @return void
     */
    protected function listenForSignals(): void
    {
        pcntl_async_signals(true);

        foreach ([SIGINT, SIGTERM, SIGALRM] as $signal) {
            pcntl_signal($signal, function () {
                $this->shouldQuit = true;
            });
        }
    }
}
