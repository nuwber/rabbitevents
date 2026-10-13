<?php

declare(strict_types=1);

namespace RabbitEvents\Listener\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Listener
{
    public function __construct(
        public readonly string $event,
        public readonly ?int $tries = null,
        public readonly ?int $timeout = null,
        public readonly int|array|null $backoff = null,
    ) {
    }
}
