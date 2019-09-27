<?php

namespace Nuwber\Events\Console\Log;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;

abstract class Writer
{

    const STATUS_PROCESSING = 'Processing';
    const STATUS_PROCESSED = 'Processed';
    const STATUS_FAILED = 'Failed';

    /**
     * @param JobProcessing | JobProcessed | JobFailed $event
     */
    abstract public function log($event);

    /**
     * @param JobProcessing | JobProcessed | JobFailed $event
     * @return string
     */
    protected function getStatus($event)
    {
        switch (get_class($event)) {
            case JobProcessing::class:
                return self::STATUS_PROCESSING;
            case JobProcessed::class:
                return self::STATUS_PROCESSED;
            case JobFailed::class:
                return self::STATUS_FAILED;
        }
    }
}
