<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Contracts;

use Throwable;

interface HandlesFailedMessages
{
    /**
     * Handle a message that has failed permanently.
     *
     * @param TransportMessage $message
     * @param Throwable $exception
     * @return void
     */
    public function fail(TransportMessage $message, Throwable $exception): void;
}
