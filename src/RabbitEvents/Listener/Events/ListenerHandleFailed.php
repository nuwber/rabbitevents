<?php

declare(strict_types=1);

namespace RabbitEvents\Listener\Events;

use RabbitEvents\Listener\Message\Handler;

class ListenerHandleFailed
{
    public function __construct(public readonly Handler $handler, public readonly \Throwable $exception)
    {
    }
}
