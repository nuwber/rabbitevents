<?php

namespace Nuwber\Events\Tests\Queue\Stubs;

class ListenerMiddleware
{
    public static $calledTimes = 0;

    public function handle($event, $payload = null)
    {
        if (func_num_args() == 1) {
            $payload = $event;
        }

        self::$calledTimes++;

        return $payload;
    }

    public function action($event, $payload = null) {
        return $this->handle($event, $payload);
    }
}
