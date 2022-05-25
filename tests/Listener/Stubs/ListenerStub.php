<?php

namespace RabbitEvents\Tests\Listener\Stubs;

class ListenerStub
{
    public function handle(): array
    {
        return func_get_args();
    }

    public function middleware(): array
    {
        return func_get_args();
    }
}
