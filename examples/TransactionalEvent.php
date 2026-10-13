<?php

declare(strict_types=1);

namespace App\RabbitEvents;

use Illuminate\Support\Facades\DB;
use RabbitEvents\Publisher\ShouldPublish;
use RabbitEvents\Publisher\Support\Publishable;

/**
 * Example of an event published only AFTER the active database transaction commits.
 * This prevents race conditions where another microservice handles the message
 * before data is committed in the database.
 */
class TransactionalEvent implements ShouldPublish
{
    use Publishable;

    /**
     * Publish this event only after the active DB transaction successfully commits.
     */
    public bool $afterCommit = true;

    public function __construct(
        public readonly int $orderId,
        public readonly string $status,
        public readonly float $amount
    ) {
    }

    public function publishEventKey(): string
    {
        return 'order.status.changed';
    }

    public function toPublish(): array
    {
        return [
            'order_id' => $this->orderId,
            'status' => $this->status,
            'amount' => $this->amount,
            'timestamp' => time(),
        ];
    }
}

// Usage inside a DB transaction:
/*
DB::transaction(function () {
    $order = Order::create([...]);

    // The message is queued and only published once the transaction commits:
    TransactionalEvent::publish($order->id, 'paid', 99.95);
});
*/
