<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Support;

use RabbitEvents\Foundation\Contracts\QueueName as QueueNameResolverInterface;

class MultiBindQueueName implements QueueNameResolverInterface
{
    public function __construct(private string $applicationName, private array $events)
    {
    }

    public function resolve(): string
    {
        return $this->applicationName . ":" . implode(',', $this->events);
    }
}
