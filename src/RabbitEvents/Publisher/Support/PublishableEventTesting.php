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

    public static function assertPublished(
        callable|string|array|null $event = null,
        ?array $payload = null
    ): void {
        Container::getInstance()->get(Publisher::class)
            ->shouldHaveReceived()
            ->publish(static::createPublishMatcher($event, $payload))
            ->atLeast()
            ->once();
    }

    public static function assertPublishedTimes(
        int $times,
        callable|string|array|null $event = null,
        ?array $payload = null
    ): void {
        Container::getInstance()->get(Publisher::class)
            ->shouldHaveReceived()
            ->publish(static::createPublishMatcher($event, $payload))
            ->times($times);
    }

    private static function createPublishMatcher(
        callable|string|array|null $event = null,
        ?array $payload = null
    ): \Mockery\Matcher\Closure {
        return \Mockery::on(static function (ShouldPublish $object) use ($event, $payload) {
            if (!$object instanceof static) {
                return false;
            }

            if (is_callable($event)) {
                return (bool) $event($object, $object->toPublish());
            }

            if (is_array($event) && is_null($payload)) {
                $payload = $event;
                $event = null;
            }

            if (is_string($event) && $object->publishEventKey() !== $event) {
                return false;
            }

            return is_null($payload) || $object->toPublish() === $payload;
        });
    }

    public static function assertNotPublished(callable|string|null $callback = null): void
    {
        Container::getInstance()->make(Publisher::class)
            ->shouldNotHaveReceived()
            ->publish(static::createPublishMatcher($callback));
    }

    public static function assertNothingPublished(): void
    {
        Container::getInstance()->make(Publisher::class)
            ->shouldNotHaveReceived()
            ->publish();
    }
}
