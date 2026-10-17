<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Deduplication;

use RabbitEvents\Foundation\Contracts\Deduplicator;
use RabbitEvents\Foundation\Contracts\TransportMessage;

class InMemoryDeduplicator implements Deduplicator
{
    private array $seen = [];

    public function __construct(private int $capacity = 10000)
    {
    }

    public function isDuplicate(TransportMessage $message): bool
    {
        $id = $message->getMessageId();
        if ($id === null || $id === '') {
            return false;
        }

        return isset($this->seen[$id]);
    }

    public function markHandled(TransportMessage $message): void
    {
        $id = $message->getMessageId();
        if ($id === null || $id === '') {
            return;
        }

        unset($this->seen[$id]);
        $this->seen[$id] = true;

        if (count($this->seen) > $this->capacity) {
            reset($this->seen);
            unset($this->seen[key($this->seen)]);
        }
    }

    public function clear(): void
    {
        $this->seen = [];
    }

    public function count(): int
    {
        return count($this->seen);
    }

    public function prepareQueue(mixed $queue): void
    {
    }
}
