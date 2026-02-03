<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Serialization;

use RabbitEvents\Foundation\Contracts\ContentType;

class JsonContentType implements ContentType
{
    private string $contentType = 'application/json';

    public function __toString(): string
    {
        return $this->contentType;
    }

    public function getValue(): string
    {
        return $this->contentType;
    }
}
