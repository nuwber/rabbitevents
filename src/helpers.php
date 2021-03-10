<?php

use Illuminate\Support\Arr;
use Nuwber\Events\Event\Publisher;
use Nuwber\Events\Event\ShouldPublish;

if (!function_exists('publish')) {

    function publish($event, array $payload = [])
    {
        if (is_string($event)) {
            $event = new class($event, $payload) implements ShouldPublish {
                private $event;
                private $payload;

                public function __construct(string $event, array $payload = [])
                {
                    $this->event = $event;
                    $this->payload = Arr::isAssoc($payload) ? [$payload] : Arr::wrap($payload);
                }

                public function publishEventKey(): string
                {
                    return $this->event;
                }

                public function toPublish(): array
                {
                    return $this->payload;
                }
            };
        }

        app(Publisher::class)->publish($event);
    }

}
