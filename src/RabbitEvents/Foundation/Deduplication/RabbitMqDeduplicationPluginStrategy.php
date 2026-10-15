<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Deduplication;

use RabbitEvents\Foundation\Contracts\Deduplicator;
use RabbitEvents\Foundation\Contracts\TransportMessage;

class RabbitMqDeduplicationPluginStrategy implements Deduplicator
{
    public function isDuplicate(TransportMessage $message): bool
    {
        // When the RabbitMQ message deduplication plugin is enabled on the broker,
        // duplicates are automatically dropped by RabbitMQ before delivery to consumer.
        return false;
    }

    public function markHandled(TransportMessage $message): void
    {
        // Broker manages deduplication state automatically.
    }

    public function prepareQueue(mixed $queue): void
    {
        if (method_exists($queue, 'setArgument')) {
            $queue->setArgument('x-message-deduplication', true);
        }
    }
}
