<?php

namespace Nuwber\Events\Queue\Events;

use Illuminate\Contracts\Queue\Job;

class JobProcessing
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
