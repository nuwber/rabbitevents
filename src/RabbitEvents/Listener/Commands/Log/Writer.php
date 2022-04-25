<?php

namespace RabbitEvents\Listener\Commands\Log;

use RabbitEvents\Listener\Events\ListenerHandlerExceptionOccurred;
use RabbitEvents\Listener\Events\ListenerHandled;
use RabbitEvents\Listener\Events\ListenerHandling;
use RabbitEvents\Listener\Events\ListenerHandleFailed;
use RabbitEvents\Listener\Events\MessageProcessingFailed;
use RabbitEvents\Listener\Events\WorkerStopping;

abstract class Writer
{
    public const STATUS_PROCESSING = 'Processing';
    public const STATUS_PROCESSED = 'Processed';
    public const STATUS_EXCEPTION = 'Exception Occurred';
    public const STATUS_FAILED = 'Failed';

    abstract public function log($event): void;

    /**
     * @return string
     */
    protected function getStatus($event): string
    {
        return match (get_class($event)) {
            ListenerHandling::class => self::STATUS_PROCESSING,
            ListenerHandled::class => self::STATUS_PROCESSED,
            ListenerHandlerExceptionOccurred::class => self::STATUS_EXCEPTION,
            default => self::STATUS_FAILED
        };
    }
}
