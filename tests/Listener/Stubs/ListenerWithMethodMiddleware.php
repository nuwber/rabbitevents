<?php

namespace RabbitEvents\Tests\Listener\Stubs;

class ListenerWithMethodMiddleware
{
    protected int $middlewareCalls = 0;
    protected int $handleCalls = 0;

    public function handle($payload): int
    {
        $this->handleCalls++;

        return $this->middlewareCalls | $this->handleCalls;
    }

    public function middleware($event, $payload = null)
    {
        if (func_num_args() === 1) {
            $payload = $event;
        }

        $this->middlewareCalls++;

        return $payload;
    }
}
