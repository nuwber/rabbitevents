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
     * @return ContentType
     */
    public function contentType(): ContentType;

    /**
     * Determine if the serializer can serialize the payload.
     *
     * @param mixed $payload
     * @return bool
     */
    public function canSerialize(mixed $payload): bool;
}
