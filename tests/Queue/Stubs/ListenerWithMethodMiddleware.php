<?php

namespace Nuwber\Events\Tests\Queue\Stubs;


class ListenerWithMethodMiddleware
{
    protected $middlewareCalls = 0;
    protected $handleCalls = 0;

    public function handle($payload)
    {
        $this->handleCalls++;

        return $this->middlewareCalls | $this->handleCalls;
    }

    public function middleware($event, $payload = null)
    {
        if (func_num_args() == 1) {
            $payload = $event;
        }

        $this->middlewareCalls++;

        return $payload;
    }
}
