<?php

namespace Nuwber\Events\Event;

use Illuminate\Container\Container;

trait Publishable
{
    protected static $recording = false;

    public static function fake()
    {
        static::$recording = true;

        Container::getInstance()->instance(Publisher::class, \Mockery::spy(Publisher::class));
    }

    public static function assertPublished(string $event, array $payload)
    {
        Container::getInstance()
            ->make(Publisher::class)
            ->shouldHaveReceived()
            ->publish(\Mockery::on(function (ShouldPublish $object) use ($event, $payload) {
                return $object instanceof static
                    && $object->publishEventKey() == $event
                    && $object->toPublish() == $payload;
            }))
            ->once();
    }

    public static function assertNotPublished()
    {
        Container::getInstance()
            ->make(Publisher::class)
            ->shouldNotHaveReceived()
            ->publish(\Mockery::type(static::class));
    }

    public static function publish(): void
    {
        Container::getInstance()
            ->make(Publisher::class)
            ->publish(
                new static(...func_get_args())
            );
    }
}

