<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Contracts;

interface Deduplicator
{
    /**
     * Determine if the message is a duplicate.
     *
     * @param TransportMessage $message
     * @return bool
     */
    public function isDuplicate(TransportMessage $message): bool;

    /**
     * Mark the message as handled.
     *
     * @param TransportMessage $message
     * @return void
     */
    public function markHandled(TransportMessage $message): void;
}
