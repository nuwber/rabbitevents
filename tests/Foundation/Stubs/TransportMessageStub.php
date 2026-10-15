<?php

namespace RabbitEvents\Tests\Foundation\Stubs;

use RabbitEvents\Foundation\Contracts\TransportMessage;

class TransportMessageStub implements TransportMessage
{
    private array $properties = [];
    private string $body;
    private $origin;

    public function __construct(string $body = '', array $properties = [], $origin = null)
    {
        $this->body = $body;
        $this->properties = $properties;
        $this->origin = $origin;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getProperty(string $name, mixed $default = null): mixed
    {
        return $this->properties[$name] ?? $default;
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getOrigin(): mixed
    {
        return $this->origin ?? $this;
    }

    public function setProperty(string $name, mixed $value): void
    {
        $this->properties[$name] = $value;
    }

    public function getRoutingKey(): ?string
    {
        return $this->getProperty('routing_key', $this->getProperty('event'));
    }

    public function setRoutingKey(?string $routingKey): void
    {
        $this->setProperty('routing_key', $routingKey);
    }

    public function getTimestamp(): ?int
    {
        return $this->getProperty('timestamp');
    }

    public function setTimestamp(int $timestamp): void
    {
        $this->setProperty('timestamp', $timestamp);
    }

    public function getMessageId(): ?string
    {
        return $this->getProperty('message_id');
    }

    public function setMessageId(?string $messageId): void
    {
        $this->setProperty('message_id', $messageId);
    }

    public function getHeader(string $name, mixed $default = null): mixed
    {
        $headers = $this->getHeaders();
        return $headers[$name] ?? $default;
    }

    public function getHeaders(): array
    {
        return (array) $this->getProperty('headers', []);
    }

    public function setHeader(string $name, mixed $value): void
    {
        $headers = $this->getHeaders();
        $headers[$name] = $value;
        $this->setHeaders($headers);
    }

    public function setHeaders(array $headers): void
    {
        $this->setProperty('headers', $headers);
    }
}
