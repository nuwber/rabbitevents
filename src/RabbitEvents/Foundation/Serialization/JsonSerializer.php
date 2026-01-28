<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Serialization;

use RabbitEvents\Foundation\Contracts\Payload;
use RabbitEvents\Foundation\Contracts\Serializer;
use RabbitEvents\Foundation\Support\JsonPayload;

class JsonSerializer implements Serializer
{
    /**
     * @inheritDoc
     */
    public function serialize(mixed $payload): Payload
    {
        return new JsonPayload($payload);
    }

    /**
     * @inheritDoc
     * @throws \JsonException
     */
    public function deserialize(string $payload, array $properties = []): Payload
    {
        return new JsonPayload(json_decode($payload, true, 512, JSON_THROW_ON_ERROR));
    }

    public function contentType(): string
    {
        return 'application/json';
    }
}
