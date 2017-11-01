<?php

namespace Nuwber\Events\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Interop\Queue\PsrConsumer;
use Interop\Queue\PsrContext;
use Nuwber\Events\ConsumerFactory;
use Nuwber\Events\Job;
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
                            {--tries=0 : Number of times to attempt a job before logging it failed}';

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
     * Execute the console command.
     *
     * @throws \Exception
     * @return mixed
     */
    public function handle()
    {
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
     * Listen for the queue events in order to update the console output.
     *
     * @return void
     */
    protected function listenForEvents()
    {
        $this->laravel['events']->listen(JobProcessing::class, function ($event) {
            $this->writeOutput($event->job, 'starting');
        });

        $this->laravel['events']->listen(JobProcessed::class, function ($event) {
            $this->writeOutput($event->job, 'success');
        });

        $this->laravel['events']->listen(JobFailed::class, function ($event) {
            $this->writeOutput($event->job, 'failed');

            $this->logFailedJob($event);
        });
    }

    /**
     * Write the status output for the queue worker.
     *
     * @param  Job $listener
     * @param  string $status
     * @return void
     */
    protected function writeOutput(Job $listener, $status)
    {
        switch ($status) {
            case 'starting':
                $this->writeStatus($listener, 'Processing', 'comment');
                break;
            case 'success':
                $this->writeStatus($listener, 'Processed', 'info');
                break;
            case 'failed':
                $this->writeStatus($listener, 'Failed', 'error');
        }
    }

    /**
     * Format the status output for the queue worker.
     *
     * @param  Job $listener
     * @param  string $status
     * @param  string $type
     * @return void
     */
    protected function writeStatus(Job $listener, $status, $type)
    {
        $this->output->writeln(sprintf(
            "<{$type}>[%s] %s</{$type}> %s",
            Carbon::now()->format('Y-m-d H:i:s'),
            str_pad("{$status}:", 11),
            $listener->resolveName()
        ));
    }

    /**
     * Store a failed job event.
     *
     * @param  \Illuminate\Queue\Events\JobFailed $event
     * @return void
     */
    protected function logFailedJob(JobFailed $event)
    {
        $this->laravel['log']->debug($event->job->getRawBody(), $event->exception->getTrace());
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
}
