<?php

namespace Nuwber\Events\Queue\Events;

use Illuminate\Queue\Jobs\Job;

class JobProcessed
{
    /**
     * @var Job
     */
    public $job;

    public function __construct(Job $job)
    {
        $this->job = $job;
    }
}
