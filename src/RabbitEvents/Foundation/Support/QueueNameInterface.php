<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Support;

use RabbitEvents\Foundation\Contracts\QueueNameInterface as QueueNameResolverInterface;

class QueueNameInterface implements QueueNameResolverInterface
{
    public function __construct(private readonly string $applicationName, private readonly array $events)
    {
    }

    public function resolve(): string
    {
        return $this->applicationName . ":" . implode(',', $this->events);
    }
}
