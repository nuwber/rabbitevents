<?php

namespace RabbitEvents\Tests\Listener\Stubs;

class ListenerWithAttributeMiddleware
{
    public array $middleware = [
        'RabbitEvents\Tests\Listener\Stubs\ListenerMiddleware@action',
        ListenerMiddleware::class
    ];

    public function __construct()
    {
        ListenerMiddleware::$calledTimes = 0;
    }

    public function handle($payload): int
    {
        return ListenerMiddleware::$calledTimes;
    }
}
