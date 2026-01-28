<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Contracts;

interface Serializer
{
    /**
     * Create Payload object from data.
     *
     * @param mixed $payload
     * @return Payload
     */
    public function serialize(mixed $payload): Payload;

    /**
     * Deserialize payload from string to Payload object.
     *
     * @param string $payload
     * @param array $properties
     * @return Payload
     */
    public function deserialize(string $payload, array $properties = []): Payload;

    /**
     * Get Content-Type of the serializer
     *
     * @return string
     */
    public function contentType(): string;
}
