<?php

namespace Nuwber\Events;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Interop\Amqp\AmqpConsumer;
use PhpAmqpLib\Exception\AMQPRuntimeException;

class Worker
{
    /**
     * Indicates if the listener should exit.
     *
     * @var bool
     */
    public $shouldQuit;

    /** @var AmqpConsumer */
    private $consumer;

    /**
     * @var MessageProcessor
     */
    private $processor;

    /** @var ExceptionHandler */
    private $exceptions;

    public function __construct(AmqpConsumer $consumer, MessageProcessor $processor, ExceptionHandler $exceptions)
    {
        $this->consumer = $consumer;
        $this->processor = $processor;
        $this->exceptions = $exceptions;
    }

    public function work(ProcessingOptions $options)
    {
        $this->listenForSignals();

        while (true) {
            if ($message = $this->getNextMessage($options->timeout)) {
                $this->processor->process($message);

                $this->consumer->acknowledge($message);
            }
            $this->stopIfNecessary($options);
        }
    }

    /**
     * Receive next message from queuer
     *
     * @param int $timeout
     * @return \Interop\Amqp\AmqpMessage|null
     */
    protected function getNextMessage(int $timeout = 0)
    {
        try {
            return $this->consumer->receive($timeout);
        } catch (\Exception $e) {
            $this->exceptions->report($e);

            $this->stopListeningIfLostConnection($e);
        }
    }

    protected function stopListeningIfLostConnection($exception)
    {
        if ($exception instanceof AMQPRuntimeException) {
            $this->shouldQuit = true;
        }
    }

    /**
     * Stop the process if necessary.
     *
     * @param  ProcessingOptions $options
     */
    protected function stopIfNecessary(ProcessingOptions $options)
    {
        if ($this->shouldQuit) {
            $this->stop();
        }

        if ($this->memoryExceeded($options->memory)) {
            $this->stop(12);
        }
    }

    /**
     * Determine if the memory limit has been exceeded.
     *
     * @param  int $memoryLimit
     * @return bool
     */
    protected function memoryExceeded($memoryLimit)
    {
        return (memory_get_usage(true) / 1024 / 1024) >= $memoryLimit;
    }

    /**
     * Stop listening and bail out of the script.
     *
     * @param  int $status
     * @return void
     */
    protected function stop($status = 0)
    {
        exit($status);
    }

    /**
     * Enable async signals for the process.
     *
     * @return void
     */
    protected function listenForSignals()
    {
        pcntl_async_signals(true);

        foreach ([SIGINT, SIGTERM, SIGALRM] as $signal) {
            pcntl_signal($signal, function () {
                $this->shouldQuit = true;
            });
        }
    }
}
