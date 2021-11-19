<?php

namespace Nuwber\Events\Event\Testing;

use Illuminate\Container\Container;
use Nuwber\Events\Event\Publisher;
use Nuwber\Events\Event\ShouldPublish;

trait PublishableEventTesting
{
    public static function fake(): void
    {
        Container::getInstance()->instance(Publisher::class, \Mockery::spy(Publisher::class));
    }

    public static function assertPublished(string $event, array $payload = null): void
    {
        Container::getInstance()->get(Publisher::class)
            ->shouldHaveReceived()
            ->publish(\Mockery::on(function (ShouldPublish $object) use ($event, $payload) {
                return $object instanceof static
                    && $object->publishEventKey() == $event
                    && (is_null($payload) || $object->toPublish() == $payload);
            }))
            ->once();
    }

    public static function assertNotPublished(): void
    {
        Container::getInstance()->get(Publisher::class)
            ->shouldNotHaveReceived()
            ->publish(\Mockery::type(static::class));
    }
}
