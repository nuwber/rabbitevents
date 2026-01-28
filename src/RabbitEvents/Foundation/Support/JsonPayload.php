<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Support;

use RabbitEvents\Foundation\Contracts\Payload;

class JsonPayload implements Payload
{
    public function __construct(private array|\JsonSerializable $value)
    {
    }

    public function value(): mixed
    {
        return $this->value;
    }

    /**
     * @return string
     * @throws \JsonException
     */
    public function serialize(): string
    {
        return json_encode($this->value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public function contentType(): string
    {
        return 'application/json';
    }
}
