<?php

declare(strict_types=1);

namespace RabbitEvents\Publisher\Support;

use RabbitEvents\Publisher\PendingPublish;
use function publish;

trait Publishable
{
    /**
     * @throws \Throwable
     */
    public static function publish(): PendingPublish
    {
        return publish(new static(...func_get_args()));
    }
}
