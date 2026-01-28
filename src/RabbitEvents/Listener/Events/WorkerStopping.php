<?php

declare(strict_types=1);

namespace RabbitEvents\Listener\Events;

class WorkerStopping
{
    public function __construct(public readonly int $status = 0)
    {
    }
}
