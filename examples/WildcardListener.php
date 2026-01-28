<?php

declare(strict_types=1);

namespace App\Listeners;

class WildcardListener
{
    public function handle(string $event, array $payload): void
    {
        match ($event) {
            'something.happened' => new Listeners\Action($payload),
            'something.else' => new Listeners\AnotherAction($payload),
            default => throw new \Exception("Unknown event $event"),
        };
    }

    public function middleware(string $event, array $payload): ?bool
    {
        // Stops the propagation if return `false`
        return true;
    }
}
