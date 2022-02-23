<?php

namespace RabbitEvents\Tests\Listener\Stubs;

class ListenerMiddleware
{
    public static int $calledTimes = 0;

    public function handle($event, $payload = null)
    {
        if (func_num_args() == 1) {
            $payload = $event;
        }

        self::$calledTimes++;

        return $payload;
    }

    public function action($event, $payload = null)
    {
        return $this->handle($event, $payload);
    }

    public static function staticMiddleware($event, $payload = null)
    {
        return (new static())->handle($event, $payload);
    }
}
