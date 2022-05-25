<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Support;

use ArrayAccess;
use Illuminate\Support\Arr;
use RabbitEvents\Foundation\Contracts\Payload as PayloadInterface;

class Payload implements PayloadInterface
{
    public function __construct(private array $payload)
    {
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @throws \JsonException
     */
    public static function createFromJson(string $json): static
    {
        return new static(json_decode($json, true, flags: JSON_THROW_ON_ERROR));
    }

    /**
     * @return false|string
     * @throws \JsonException
     */
    public function jsonSerialize(): false|string
    {
        return json_encode($this->payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
