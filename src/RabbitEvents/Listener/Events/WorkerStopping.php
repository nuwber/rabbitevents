<?php

namespace RabbitEvents\Listener\Events;

class WorkerStopping
{
    public function __construct(public readonly int $status = 0)
    {
    }
}
