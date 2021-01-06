<?php

namespace Nuwber\Events\Console\Log;

use Illuminate\Contracts\Foundation\Application;
use Nuwber\Events\Queue\Jobs\Job;

class General extends Writer
{
    /**
     * @var Application
     */
    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * @inheritdoc
     */
    public function log($event)
    {
        $status = $this->getStatus($event);

        $this->write($event->job, $status);
    }

    protected function write(Job $job, $status)
    {
        $this->app['log']->log($this->getLogLevel($status), sprintf('Job "%s" %s', $job->getName(), $status), [
            'job' => [
                'name' => $job->getName(),
                'attempts' => $job->attempts(),
                'payload' => $job->payload(),
            ],
            'status' => $status,
        ]);
    }

    protected function getLogLevel($status)
    {
        if ($status == static::STATUS_EXCEPTION || $status == static::STATUS_FAILED) {
            return 'error';
        }

        $connection = $this->app['config']->get('queue.default', 'rabbitmq');

        return $this->app['config']->get("queue.connections.$connection.logging.level", 'info');
    }
}
