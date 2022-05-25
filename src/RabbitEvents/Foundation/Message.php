<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation;

use Illuminate\Container\Container;
use Interop\Amqp\AmqpMessage;
use JsonSerializable;
use RabbitEvents\Foundation\Amqp\MessageFactory;
use RabbitEvents\Foundation\Contracts\Transport;
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
        private Transport $transport,
        private array $properties = []
    ) {
    }

    /**
     * @param AmqpMessage $amqpMessage
     * @param Transport|null $transport
     * @return static
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \JsonException
     */
    public static function createFromAmqpMessage(AmqpMessage $amqpMessage, ?Transport $transport = null): self
    {
        $message = new static(
            $amqpMessage->getRoutingKey(),
            Payload::createFromJson($amqpMessage->getBody()),
            $transport ?: Container::getInstance()->make(Context::class)->getTransport(),
            $amqpMessage->getProperties()
        );
        $message->setAmqpMessage($amqpMessage);

        return $message;
    }

    /**
     * @param int $delay
     */
    public function send(int $delay = 0): void
    {
        $this->transport->send($this, $delay);
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
        return $this->amqpMessage()->getProperty('x-attempts', 0);
    }

    public function increaseAttempts(): self
    {
        $this->amqpMessage()->setProperty('x-attempts', $this->attempts() + 1);

        return $this;
    }

    /**
     * @param int $delay
     */
    public function release(int $delay = 0): void
    {
        $this->amqpMessage = clone $this->amqpMessage();

        $this->send($delay);
    }

    /**
     * @param AmqpMessage $amqpMessage
     */
    public function setAmqpMessage(AmqpMessage $amqpMessage): self
    {
        $this->amqpMessage = $amqpMessage;

        return $this;
    }
}
