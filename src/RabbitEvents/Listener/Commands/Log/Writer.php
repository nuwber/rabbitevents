<?php

namespace RabbitEvents\Listener\Commands\Log;

use RabbitEvents\Listener\Events\HandlerExceptionOccurred;
use RabbitEvents\Listener\Events\MessageProcessed;
use RabbitEvents\Listener\Events\MessageProcessing;
use RabbitEvents\Listener\Events\MessageProcessingFailed;

abstract class Writer
{
    public const STATUS_PROCESSING = 'Processing';
    public const STATUS_PROCESSED = 'Processed';
    public const STATUS_EXCEPTION = 'Exception Occurred';
    public const STATUS_FAILED = 'Failed';

    /**
     * @param HandlerExceptionOccurred | MessageProcessing | MessageProcessed | MessageProcessingFailed $event
     */
    abstract public function log($event): void;

    /**
     * @param HandlerExceptionOccurred | MessageProcessing | MessageProcessed | MessageProcessingFailed $event
     * @return string
     */
    protected function getStatus($event): string
    {
        return match (get_class($event)) {
            MessageProcessing::class => self::STATUS_PROCESSING,
            MessageProcessed::class => self::STATUS_PROCESSED,
            HandlerExceptionOccurred::class => self::STATUS_EXCEPTION,
            MessageProcessingFailed::class => self::STATUS_FAILED,
        };
    }
}
