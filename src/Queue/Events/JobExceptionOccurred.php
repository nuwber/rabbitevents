<?php

namespace Nuwber\Events\Queue\Events;

use Illuminate\Contracts\Queue\Job;

class JobExceptionOccurred
{
    /**
     * @var Job
     */
    public $job;

    /**
     * @var \Throwable
     */
    public $exception;

    public function __construct(Job $job, \Throwable $exception)
    {
        $this->job = $job;
        $this->exception = $exception;
    }
}
