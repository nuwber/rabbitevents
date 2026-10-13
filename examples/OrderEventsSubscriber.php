<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use RabbitEvents\Listener\Attributes\Listener;

/**
 * Example demonstrating multiple attribute-based listeners in a single class
 * to organize event handling cleanly.
 */
class OrderEventsSubscriber
{
    #[Listener('order.created')]
    public function onOrderCreated(array $payload): void
    {
        Log::info('Order created', ['order_id' => $payload['order_id'] ?? null]);
    }

    #[Listener('order.paid')]
    public function onOrderPaid(array $payload): void
    {
        Log::info('Order paid', ['order_id' => $payload['order_id'] ?? null]);
    }

    #[Listener('order.cancelled')]
    public function onOrderCancelled(array $payload): void
    {
        Log::warning('Order cancelled', [
            'order_id' => $payload['order_id'] ?? null,
            'reason' => $payload['reason'] ?? 'Unknown',
        ]);
    }
}
