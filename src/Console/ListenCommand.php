<?php

namespace Nuwber\Events\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Interop\Queue\PsrConsumer;
use Interop\Queue\PsrContext;
use Nuwber\Events\ConsumerFactory;
use Nuwber\Events\Logging\Output as OutputWriter;
use Nuwber\Events\Logging\General as GeneralWriter;
use Nuwber\Events\MessageProcessor;
use Nuwber\Events\ProcessingOptions;
use PhpAmqpLib\Exception\AMQPRuntimeException;

class ListenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'events:listen
                            {--memory=128 : The memory limit in megabytes}
                            {--timeout=60 : The number of seconds a child process can run}
                            {--tries=0 : Number of times to attempt a job before logging it failed}
                            {--quiet: No console output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listen for system events thrown from other services';

    /**
     * Indicates if the listener should exit.
     *
     * @var bool
     */
    private $shouldQuit;

    /**
     * @var array
     */
    protected $logWriters = [];

    /**
     * Execute the console command.
     *
     * @throws \Exception
     * @return mixed
     */
    public function handle()
    {
        $this->registerLogWriters();

        $this->listenForEvents();
        $this->listenForSignals();

        $consumer = $this->makeConsumer();

        $options = $this->gatherProcessingOptions();

        $processor = $this->createProcessor($options);

        while (true) {
            if ($payload = $this->getNextJob($consumer, $options)) {
                $processor->process($consumer, $payload);
            }
            $this->stopIfNecessary($options);
        }
    }

    /**
     * Receive next message from queuer
     *
     * @param PsrConsumer $consumer
     * @param $options
     * @return \Interop\Queue\PsrMessage|null
     */
    protected function getNextJob(PsrConsumer $consumer, $options)
    {
        try {
            return $consumer->receive($options->timeout);
        } catch (\Exception $e) {
            $this->laravel->make(ExceptionHandler::class)->report($e);

            $this->stopListeningIfLostConnection($e);
        } catch (\Throwable $e) {
            $this->laravel->make(ExceptionHandler::class)->report($e);

            $this->stopListeningIfLostConnection($e);
        }
    }

    /**
     * @param ProcessingOptions $options
     * @return MessageProcessor
     */
    protected function createProcessor(ProcessingOptions $options)
    {
        return new MessageProcessor(
            $this->laravel,
            $this->laravel->make(PsrContext::class),
            $this->laravel->make('events'),
            $this->laravel->make('broadcast.events'),
            $options,
            $this->laravel->make('queue')->getConnectionName(),
            $this->laravel->make(ExceptionHandler::class)
        );
    }

    /**
     * @return PsrConsumer
     */
    private function makeConsumer()
    {
        return $this->laravel->make(ConsumerFactory::class)
            ->make(
                $this->laravel->make('broadcast.events')->getEvents()
            );
    }

    /**
     * Gather all of the queue worker options as a single object.
     *
     * @return ProcessingOptions
     */
    protected function gatherProcessingOptions()
    {
        return new ProcessingOptions(
            $this->option('memory'),
            $this->option('timeout'),
            $this->option('tries')
        );
    }

    /**
     * Listen for the queue events in order to update the console output.
     *
     * @return void
     */
    protected function listenForEvents()
    {
        $callback = function ($event) {
            foreach ($this->logWriters as $writer) {
                $writer->log($event);
            }
        };

        $this->laravel['events']->listen(JobProcessing::class, $callback);
        $this->laravel['events']->listen(JobProcessed::class, $callback);
        $this->laravel['events']->listen(JobFailed::class, $callback);
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

    protected function stopListeningIfLostConnection($exception)
    {
        if ($exception instanceof AMQPRuntimeException) {
            $this->shouldQuit = true;
        }
    }

    private function registerLogWriters()
    {
        if (!$this->option('quiet')) {
            $this->logWriters[] = new OutputWriter($this->laravel, $this->output);
        }

        if ($this->laravel['config']->get('queue.connections.interop.logging.enabled')) {
            $this->logWriters[] = new GeneralWriter($this->laravel);
        }
    }
}
