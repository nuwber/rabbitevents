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
        $name = $this->applicationName . ':' . implode(',', $this->events);

        if (mb_strlen($name, 'ASCII') > 255) {
            $name = $this->applicationName . ':' . md5($name);
        }

        return $name;
    }
}
