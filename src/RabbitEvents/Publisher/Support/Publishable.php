<?php

declare(strict_types=1);

namespace RabbitEvents\Publisher\Support;

use RabbitEvents\Publisher\PendingPublish;
use RabbitEvents\Publisher\ShouldPublish;

trait Publishable
{
    /**
     * @throws \Throwable
     */
    public static function publish(): void
    {
        static::pending(...func_get_args())->publish();
    }

    public static function pending(): PendingPublish
    {
        /** @var ShouldPublish $event */
        $event = new static(...func_get_args());
        return new PendingPublish($event);
    }
}
