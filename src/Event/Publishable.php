<?php

namespace Nuwber\Events\Event;

trait Publishable
{
    /**
     * @return mixed
     * @throws \Throwable
     */
    public static function publish(): void
    {
        publish(new static(...func_get_args()));
    }
}
