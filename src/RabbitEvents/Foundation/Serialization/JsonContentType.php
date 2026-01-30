<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Serialization;

use RabbitEvents\Foundation\Contracts\ContentType;

class JsonContentType implements ContentType
{
    public function __toString(): string
    {
        return 'application/json';
    }
}
