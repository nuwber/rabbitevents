<?php

declare(strict_types=1);

namespace RabbitEvents\Listener\Console\Log;

use RabbitEvents\Listener\Events\ListenerHandlerExceptionOccurred;
use RabbitEvents\Listener\Events\ListenerHandled;
use RabbitEvents\Listener\Events\ListenerHandling;

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
