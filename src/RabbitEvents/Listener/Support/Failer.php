<?php

declare(strict_types=1);

namespace RabbitEvents\Listener\Support;

use Illuminate\Support\Facades\Log;
use RabbitEvents\Foundation\Contracts\Destination;
use RabbitEvents\Foundation\Contracts\Producer;
use RabbitEvents\Foundation\Contracts\Transport;
use RabbitEvents\Foundation\Message;

class Failer implements Transport
{
    public function __construct(
        protected Destination $destination,
        protected Producer $producer,
        protected string $queueName
    ) {
    }

    public function send(Message $message): void
    {
        $exception = $message->failureException();
        $exceptionClass = $exception ? get_class($exception) : 'N/A';
        $exceptionMessage = $exception ? $exception->getMessage() : 'N/A';
        $exceptionTrace = $exception ? $exception->getTraceAsString() : '';

        // Write warning to log as specified
        try {
            if (class_exists(Log::class) && \Illuminate\Support\Facades\Facade::getFacadeApplication()) {
                Log::warning(sprintf(
                    'RabbitEvents: Message [%s] for event [%s] failed after %d attempts. Dead-lettering to [%s]. Exception: %s: %s',
                    $message->id() ?? 'unknown',
                    $message->event,
                    $message->attempts(),
                    $this->queueName . '.failed',
                    $exceptionClass,
                    $exceptionMessage
                ));
            }
        } catch (\Throwable) {
            // Log facade root not configured
        }

        // Attach diagnostic headers (B4)
        $message->setHeader('x-failed-at', time());
        $message->setHeader('x-original-routing-key', $message->getRoutingKey() ?: $message->event);
        $message->setHeader('x-exception-class', $exceptionClass);
        $message->setHeader('x-exception-message', $exceptionMessage);
        if ($exceptionTrace) {
            $message->setHeader('x-exception-trace', $exceptionTrace);
        }
        if ($failedListener = $message->failedListener()) {
            $message->setHeader('x-listener', $failedListener);
        }

        $transportMessage = $message->transportMessage();
        $transportMessage->setRoutingKey($this->queueName);

        $this->producer->send($this->destination, $transportMessage);
    }
}
