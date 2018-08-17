<?php

namespace Nuwber\Events\Logging;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Carbon;
use Nuwber\Events\Job;

class Output
{

    /**
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $laravel;

    /**
     * @var \Illuminate\Console\OutputStyle
     */
    protected $output;

    public function __construct($laravel, $output)
    {
        $this->laravel = $laravel;
        $this->output = $output;
    }

    public function register()
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
}
