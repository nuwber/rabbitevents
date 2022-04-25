<?php

namespace RabbitEvents\Listener\Events;

class WorkerStopping
{
    public function __construct(public int $status = 0)
    {
    }
}
