<?php
namespace Nuwber\Events\Event;

trait Publishable
{
    public static function publish()
    {
        return publish(new static(...func_get_args()));
    }
}
