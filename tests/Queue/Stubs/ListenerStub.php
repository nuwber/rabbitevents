<?php

namespace Nuwber\Events\Tests\Queue\Stubs;

class ListenerStub
{
    public function handle()
    {
        return func_get_args();
    }

    public function middleware()
    {
        return func_get_args();
    }
}
