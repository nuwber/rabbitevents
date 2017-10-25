<?php

namespace Nuwber\Events\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Interop\Queue\PsrConsumer;
use Interop\Queue\PsrContext;
use Nuwber\Events\ConsumerFactory;
use Nuwber\Events\Job;
use Nuwber\Events\MessageProcessor;
use Nuwber\Events\ProcessingOptions;

class ListenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'events:listen
                            {--timeout=60 : The number of seconds a child process can run}
                            {--tries=0 : Number of times to attempt a job before logging it failed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listen for system events thrown from other services';

    /**
     * Execute the console command.
     *
     * @throws \Exception
     * @return mixed
     */
    public function handle()
    {
        $this->listenForEvents();

        $consumer = $this->makeConsumer();

        $options = $this->gatherProcessingOptions();

        $processor = $this->makeMessageProcessor($options);

        while (true) {
            if ($payload = $consumer->receive($options->timeout)) {
                try {
                    $processor->process($consumer, $payload);
                } catch (\Exception $e) {
                    // Do nothing because we've already fired all necessary events
                }
            }
        }
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
        logger($event->job->getRawBody(), $event->exception->getTrace());
    }

    /**
     * @return PsrConsumer
     */
    private function makeConsumer()
    {
        return $this->laravel->make(ConsumerFactory::class)
            ->make(
                $this->laravel->make('broadcast.events')
                    ->getEvents()
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
            $this->option('timeout'),
            $this->option('tries')
        );
    }

    private function makeMessageProcessor(ProcessingOptions $options)
    {
        return new MessageProcessor(
            $this->laravel,
            $this->laravel->make(PsrContext::class),
            $this->laravel->make('events'),
            $this->laravel->make('broadcast.events'),
            $options,
            $this->laravel->make('queue')->getConnectionName()
        );
    }
}
