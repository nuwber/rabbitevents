<?php

declare(strict_types=1);

namespace RabbitEvents\Publisher\Support;

use Illuminate\Container\Container;
use RabbitEvents\Publisher\Publisher;
use RabbitEvents\Publisher\ShouldPublish;

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
            ->publish(\Mockery::on(static function (ShouldPublish $object) use ($event, $payload) {
                return $object instanceof static
                    && $object->publishEventKey() === $event
                    && (is_null($payload) || $object->toPublish() === $payload);
            }))
            ->once();
    }

    public static function assertNotPublished(): void
    {
        Container::getInstance()->make(Publisher::class)
            ->shouldNotHaveReceived()
            ->publish(\Mockery::type(static::class));
    }
}
