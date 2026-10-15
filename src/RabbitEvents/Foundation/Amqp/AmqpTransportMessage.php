<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Amqp;

use Interop\Amqp\AmqpMessage;
use RabbitEvents\Foundation\Contracts\TransportMessage;

class AmqpTransportMessage implements TransportMessage
{
    public function __construct(private AmqpMessage $message)
    {
    }

    public function getBody(): string
    {
        return $this->message->getBody();
    }

    public function getProperty(string $name, mixed $default = null): mixed
    {
        return $this->message->getProperty($name, $default);
    }

    public function getProperties(): array
    {
        return $this->message->getProperties();
    }

    public function getOrigin(): AmqpMessage
    {
        return $this->message;
    }

    public function setProperty(string $name, mixed $value): void
    {
        $this->message->setProperty($name, $value);
    }

    public function getRoutingKey(): ?string
    {
        return $this->message->getRoutingKey();
    }

    public function setRoutingKey(?string $routingKey): void
    {
        $this->message->setRoutingKey($routingKey);
    }

    public function getTimestamp(): ?int
    {
        return $this->message->getTimestamp();
    }

    public function setTimestamp(int $timestamp): void
    {
        $this->message->setTimestamp($timestamp);
    }

    public function getMessageId(): ?string
    {
        return $this->message->getMessageId();
    }

    public function setMessageId(?string $messageId): void
    {
        $this->message->setMessageId($messageId);
    }

    public function getHeader(string $name, mixed $default = null): mixed
    {
        $headers = $this->getHeaders();

        return $headers[$name] ?? $default;
    }

    public function getHeaders(): array
    {
        $headers = $this->message->getHeaders();
        $properties = $this->message->getProperties();

        $nestedHeaders = is_array($properties['headers'] ?? null) ? $properties['headers'] : [];

        return array_merge($properties, $headers, $nestedHeaders);
    }

    public function setHeader(string $name, mixed $value): void
    {
        $this->message->setHeader($name, $value);
        $this->message->setProperty($name, $value);

        $properties = $this->message->getProperties();
        if (isset($properties['headers']) && is_array($properties['headers'])) {
            $properties['headers'][$name] = $value;
            $this->message->setProperty('headers', $properties['headers']);
        }
    }

    public function setHeaders(array $headers): void
    {
        $this->message->setHeaders($headers);
        foreach ($headers as $name => $value) {
            $this->message->setProperty((string) $name, $value);
        }

        $properties = $this->message->getProperties();
        if (isset($properties['headers']) && is_array($properties['headers'])) {
            $properties['headers'] = array_merge($properties['headers'], $headers);
            $this->message->setProperty('headers', $properties['headers']);
        }
    }

    public function __call(string $method, array $args)
    {
        return $this->message->$method(...$args);
    }
}
