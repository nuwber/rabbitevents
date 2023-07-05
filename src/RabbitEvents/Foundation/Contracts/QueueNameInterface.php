<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Contracts;

interface QueueNameInterface
{
    /**
     * Return a queue name event bound to
     *
     * @return string
     */
    public function resolveQueueName(): string;
}
