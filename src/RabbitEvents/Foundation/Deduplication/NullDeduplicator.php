<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Deduplication;

use RabbitEvents\Foundation\Contracts\Deduplicator;
use RabbitEvents\Foundation\Contracts\TransportMessage;

class NullDeduplicator implements Deduplicator
{
    public function isDuplicate(TransportMessage $message): bool
    {
        return false;
    }

    public function markHandled(TransportMessage $message): void
    {
    }

    public function prepareQueue(mixed $queue): void
    {
    }
}
