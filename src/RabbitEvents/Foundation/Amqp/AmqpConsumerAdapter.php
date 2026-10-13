<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Amqp;

use Interop\Amqp\AmqpConsumer;
use RabbitEvents\Foundation\Contracts\HandlesFailedMessages;
use RabbitEvents\Foundation\Contracts\QueueConsumer;
use RabbitEvents\Foundation\Contracts\TransportMessage;

class AmqpConsumerAdapter implements QueueConsumer, HandlesFailedMessages
{
    public function __construct(private AmqpConsumer $consumer)
    {
    }

    public function receive(int $timeout = 0): ?TransportMessage
    {
        if ($message = $this->consumer->receive($timeout)) {
            return new AmqpTransportMessage($message);
        }

        return null;
    }

    public function acknowledge(TransportMessage $message): void
    {
        $this->consumer->acknowledge($message->getOrigin());
    }

    public function reject(TransportMessage $message, bool $requeue = false): void
    {
        $this->consumer->reject($message->getOrigin(), $requeue);
    }

    public function fail(TransportMessage $message, \Throwable $exception): void
    {
        $this->reject($message, false);
    }

    public function __call(string $method, array $args)
    {
        return $this->consumer->$method(...$args);
    }
}
