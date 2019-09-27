<?php

namespace Nuwber\Events\Console\Log;

use Illuminate\Contracts\Foundation\Application;
use Nuwber\Events\Queue\Job;

class General extends Writer
{
    /**
     * @var Application
     */
    protected $laravel;

    public function __construct($laravel)
    {
        $this->laravel = $laravel;
    }

    /**
     * @inheritdoc
     */
    public function log($event)
    {
        $status = $this->getStatus($event);
        $level = $status != self::STATUS_FAILED ?
            $this->laravel['config']->get('queue.connections.interop.logging.level', 'info') :
            'error';

        $this->write($event->job, $status, $level);
    }

    protected function write(Job $job, $status, $level)
    {
        $this->laravel['log']->log($level, sprintf('Job "%s" %s', $job->getName(), $status), [
            'job' => [
                'name' => $job->getName(),
                'attempts' => $job->attempts(),
                'payload' => $job->payload(),
            ],
            'status' => $status,
        ]);
    }
}
