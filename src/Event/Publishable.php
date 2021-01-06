<?php

namespace Nuwber\Events\Event;

use Illuminate\Container\Container;

trait Publishable
{
    /**
     * @return mixed
     * @throws \Throwable
     */
    public static function publish(): void
    {
        Container::getInstance()
            ->get(Publisher::class)
            ->publish(new static(...func_get_args()));
    }
}
