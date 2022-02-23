<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation;

use Illuminate\Support\Carbon;
use Interop\Amqp\AmqpConsumer;

/**
 * @mixin AmqpConsumer
 */
class Consumer
{
    public function __construct(private AmqpConsumer $amqpConsumer, private Context $context)
    {
    }

    public function __call(string $method, ?array $args)
    {
        return $this->amqpConsumer->$method(...$args);
    }

    /**
     * Receives a Message from the queue and returns Message object
     *
     * @param int $timeout
     * @return ?Message
     */
    public function nextMessage(int $timeout = 0): ?Message
    {
        if (!$amqpMessage = $this->amqpConsumer->receive($timeout)) {
            return null;
        }

        $amqpMessage->setTimestamp(Carbon::now()->timestamp);

        return Message::createFromAmqpMessage($amqpMessage, $this->context->getTransport())
            ->setAmqpMessage($amqpMessage);
    }

    public function acknowledge(Message $message): void
    {
        $this->amqpConsumer->acknowledge($message->amqpMessage());
    }
}
