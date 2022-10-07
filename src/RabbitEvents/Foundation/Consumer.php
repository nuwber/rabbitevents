<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation;

use Illuminate\Support\Carbon;
use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\AmqpMessage;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use RabbitEvents\Foundation\Contracts\Transport;
use RabbitEvents\Foundation\Exceptions\ConnectionLostException;

/**
 * @mixin AmqpConsumer
 */
class Consumer
{
    public function __construct(private AmqpConsumer $amqpConsumer)
    {
    }

    public function __call(string $method, ?array $args)
    {
        return $this->amqpConsumer->$method(...$args);
    }

    /**
     * Receives a Message from the queue and returns Message object
     * @throws \JsonException
     */
    public function nextMessage(int $timeout = 0): ?Message
    {
        if (!$amqpMessage = $this->receiveMessage($timeout)) {
            return null;
        }

        // Set timestamp only if this message was not released before
        if (!$amqpMessage->getTimestamp()) {
            $amqpMessage->setTimestamp(Carbon::now()->getTimestamp());
        }

        if (!$amqpMessage->getProperty('event')) {
            $amqpMessage->setProperty('event', $amqpMessage->getRoutingKey());
        }

        return Message::createFromAmqpMessage($amqpMessage)->increaseAttempts();
    }

    protected function receiveMessage(int $timeout = 0): ?AmqpMessage
    {
        try {
            return $this->amqpConsumer->receive($timeout);
        } catch (AMQPRuntimeException $exception) {
            throw new ConnectionLostException($exception);
        }
    }

    public function acknowledge(Message $message): void
    {
        $this->amqpConsumer->acknowledge($message->amqpMessage());
    }
}
