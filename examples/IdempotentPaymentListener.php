<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use RabbitEvents\Listener\Attributes\Listener;

/**
 * Example of an idempotent listener processing events safely.
 * Notice that RabbitEvents automatically deduplicates events using InMemoryDeduplicator
 * or the RabbitMQ plugin, and hydrates distributed tracing Context.
 */
#[Listener('payment.received')]
class IdempotentPaymentListener
{
    /**
     * Handle the event payload.
     *
     * @param array $payload The event payload sent from the publisher.
     */
    public function handle(array $payload): void
    {
        // Distributed trace ID propagated from the publishing service:
        $traceId = Context::get('trace_id');

        Log::info('Processing payment event', [
            'trace_id' => $traceId,
            'payment_id' => $payload['payment_id'] ?? null,
            'amount' => $payload['amount'] ?? 0,
        ]);

        // Process payment safely (e.g. update balance, send receipt):
        // If the same event arrives again due to network retry,
        // RabbitEvents deduplication automatically discards it.
    }
}
