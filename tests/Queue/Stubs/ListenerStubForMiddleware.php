<?php

namespace Nuwber\Events\Tests\Queue\Stubs;

class ListenerStubForMiddleware
{
    protected $middlewarePayload = null;

    public function handle()
    {
        return $this->middlewarePayload;
    }

    public function middleware()
    {
        $this->middlewarePayload = func_get_args();
    }
}
