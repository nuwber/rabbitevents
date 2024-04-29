<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation;

use Interop\Amqp\AmqpMessage;
use JsonSerializable;
use RabbitEvents\Foundation\Amqp\MessageFactory;
use RabbitEvents\Foundation\Support\Payload;

/**
 * @mixin AmqpMessage
 */
class Message
{
    /**
     * @var AmqpMessage
     */
    private $amqpMessage;

    public function __construct(
        private string $event,
        private JsonSerializable $payload,
        private array $properties = []
    ) {
    }

    /**
     * @param AmqpMessage $amqpMessage
     * @return static
     * @throws \JsonException
     */
    public static function createFromAmqpMessage(AmqpMessage $amqpMessage): self
    {
        return (new static(
            $amqpMessage->getProperty('event') ?: $amqpMessage->getRoutingKey(),
            Payload::createFromJson($amqpMessage->getBody()),
            $amqpMessage->getProperties()
        ))->setAmqpMessage($amqpMessage);
    }

    /**
     * @return AmqpMessage
     */
    public function amqpMessage(): AmqpMessage
    {
        if (is_null($this->amqpMessage)) {
            $this->amqpMessage = MessageFactory::make($this->event, $this->payload, $this->properties);
        }

        return $this->amqpMessage;
    }

    public function __call(string $method, ?array $args)
    {
        return $this->amqpMessage()->$method(...$args);
    }

    public function payload(): JsonSerializable
    {
        return $this->payload;
    }

    public function event(): string
    {
        return $this->event;
    }

    public function attempts(): int
    {
        return $this->getProperty('x-attempts', 0);
    }

    public function increaseAttempts(): self
    {
        $this->setProperty('x-attempts', $this->attempts() + 1);

        return $this;
    }

    /**
     * @param AmqpMessage $amqpMessage
     * @return Message
     */
    public function setAmqpMessage(AmqpMessage $amqpMessage): self
    {
        $this->amqpMessage = $amqpMessage;

        return $this;
    }
}
