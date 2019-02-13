<?php

namespace Nuwber\Events;

use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Application as LaravelApplication;
use Laravel\Lumen\Application as LumenApplication;
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
     * @var LaravelApplication|LumenApplication
     */
    private $app;

    /**
     * @var MessageProcessor
     */
    private $processor;

    /**
     * @param LaravelApplication|LumenApplication $app
     * @param AmqpConsumer $consumer
     * @param MessageProcessor $processor
     */
    public function __construct(Container $app, AmqpConsumer $consumer, MessageProcessor $processor)
    {
        $this->app = $app;
        $this->consumer = $consumer;
        $this->processor = $processor;
    }

    public function work(ProcessingOptions $options)
    {
        $this->listenForSignals();

        while (true) {
            if ($message = $this->getNextMessage($options)) {
                $this->processor->process($message);

                $this->consumer->acknowledge($message);
            }
            $this->stopIfNecessary($options);
        }
    }

    /**
     * Receive next message from queuer
     *
     * @param AmqpConsumer $consumer
     * @param $options
     * @return \Interop\Amqp\AmqpMessage|null
     */
    protected function getNextMessage(ProcessingOptions $options)
    {
        try {
            return $this->consumer->receive($options->timeout);
        } catch (\Exception $e) {
            $this->app->make(ExceptionHandler::class)->report($e);

            $this->stopListeningIfLostConnection($e);
        } catch (\Throwable $e) {
            $this->app->make(ExceptionHandler::class)->report($e);

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
