<?php

namespace Nuwber\Events\Tests\Queue\Stubs;

use Illuminate\Support\Arr;

class ListenerWithMethodMiddlewareReceivingArray
{
    protected $middlewarePayload = null;

    public function handle($event, $payload = null)
    {
        return $this->middlewarePayload;
    }

    public function middleware($event, $payload = null)
    {
        if (func_num_args() == 1) {
            $payload = $event;
        }

        if (is_array($payload) && Arr::isAssoc($payload)) {
            $this->middlewarePayload = $payload;    
        } 
    }
}
