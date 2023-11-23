<?php

declare(strict_types=1);

namespace RabbitEvents\Listener\Events;

use RabbitEvents\Listener\Message\Handler;

class ListenerHandled
{
    public function __construct(public readonly Handler $handler)
    {
    }
}
