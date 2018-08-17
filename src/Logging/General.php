<?php

namespace Nuwber\Events\Logging;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Arr;
use Nuwber\Events\Job;

class General
{
    /**
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $laravel;

    /**
     * @var array
     */
    protected $config;

    public function __construct($laravel)
    {
        $this->laravel = $laravel;
        $this->config = $laravel['config']->get('queue.connections.interop.logging');
    }

    public function register()
    {
        if (!Arr::get($this->config, 'enabled', false)) {
            return;
        }

        $level = Arr::get($this->config, 'level', 'info');

        $this->laravel['events']->listen(JobProcessing::class, function ($event) use ($level) {
            $this->log($event, 'processing', $level);
        });

        $this->laravel['events']->listen(JobProcessed::class, function ($event) use ($level) {
            $this->log($event, 'processed', $level);
        });

        $this->laravel['events']->listen(JobFailed::class, function ($event) use ($level) {
            $this->log($event, 'failed', $level);
        });
    }

    protected function log(Job $job, $status, $level)
    {
        $this->laravel['log']->log($level, sprintf('Job "%s" %s', $job->getName(), $status), [
            'job' => [
                'payload' => $job->payload(),
                'name' => $job->getName(),
                'connection' => $job->getConnectionName(),
                'attempts' => $job->attempts(),
                'id' => $job->getJobId(),
            ],
            'status' => $status,
        ]);
    }
}
