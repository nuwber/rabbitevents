<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Support\Arr;

class ExampleMiddleware
{
    public function handle(array $payload): bool
    {
        return Arr::get($payload, 'entity.type') === 'mytype';
    }
}
