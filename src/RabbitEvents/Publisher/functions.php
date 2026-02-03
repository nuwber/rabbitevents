<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use Illuminate\Container\Container;
use RabbitEvents\Publisher\Publisher;
use RabbitEvents\Publisher\ShouldPublish;

if (!function_exists('publish')) {
    function publish($event, $payload = [])
    {
        if (is_string($event)) {
            $event = new class ($event, $payload) implements ShouldPublish {
                private $event;
                private $payload;

                public function __construct(string $event, $payload = [])
                {
                    $this->event = $event;
                    
                    if (is_object($payload)) {
                        $this->payload = $payload;
                    } elseif (is_array($payload) && Arr::isAssoc($payload)) {
                        $this->payload = [$payload];
                    } else {
                        $this->payload = Arr::wrap($payload);
                    }
                }

                public function publishEventKey(): string
                {
                    return $this->event;
                }

                public function toPublish(): mixed
                {
                    return $this->payload;
                }
            };
        }

        Container::getInstance()
            ->make(Publisher::class)
            ->publish($event);
    }
}
