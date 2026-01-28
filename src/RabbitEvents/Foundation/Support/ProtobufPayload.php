<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Support;

use Google\Protobuf\Internal\Message;
use RabbitEvents\Foundation\Contracts\Payload;

class ProtobufPayload implements Payload
{
    public function __construct(private Message $value)
    {
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function serialize(): string
    {
        return $this->value->serializeToString();
    }

    public function contentType(): string
    {
        return 'application/x-protobuf';
    }
}
