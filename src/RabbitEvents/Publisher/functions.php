<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use RabbitEvents\Publisher\PendingPublish;
use RabbitEvents\Publisher\ShouldPublish;

if (!function_exists('publish')) {
    function publish($event, array $payload = []): PendingPublish
    {
        if (is_string($event)) {
            $event = new class ($event, $payload) implements ShouldPublish {
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
        return $event;
    }
}
