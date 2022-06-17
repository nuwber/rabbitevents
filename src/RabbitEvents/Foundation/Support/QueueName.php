<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Support;

use RabbitEvents\Foundation\Contracts\QueueName as QueueNameResolverInterface;

class QueueName implements QueueNameResolverInterface
{
    public function __construct(private string $applicationName, private string $event)
    {
    }

    public function resolve(): string
    {
        return $this->applicationName . ":" . $this->event;
    }
}
