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
     * @param TransportMessage $message
     * @return Payload
     */
    public function deserialize(TransportMessage $message): Payload;

    /**
     * Get Content-Type of the serializer
     *
     * @return string
     */
    public function contentType(): string;
}
