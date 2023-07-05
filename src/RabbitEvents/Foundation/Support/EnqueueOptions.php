<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Support;

use RabbitEvents\Foundation\Contracts\QueueNameInterface;

class EnqueueOptions implements QueueNameInterface
{
    public readonly string $name;

    public function __construct(private readonly string $applicationName, public readonly array $events)
    {
        $this->name = $this->resolveQueueName();
    }

    public function resolveQueueName(): string
    {
        return $this->applicationName . ":" . implode(',', $this->events);
    }
}
