<?php

declare(strict_types=1);

namespace RabbitEvents\Listener\Message;

use Illuminate\Contracts\Container\Container;
use RabbitEvents\Foundation\Message;

class HandlerFactory
{
    public function __construct(private Container $container)
    {
    }

    public function make(Message $message, callable $callback, string $listenerClass): Handler
    {
        return new Handler($this->container, $message, $callback, $listenerClass);
    }
}
