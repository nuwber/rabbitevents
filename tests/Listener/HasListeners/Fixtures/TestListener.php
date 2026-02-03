<?php

declare(strict_types=1);

namespace RabbitEvents\Tests\Listener\HasListeners\Fixtures;

use RabbitEvents\Listener\Attributes\Listener;

#[Listener(event: 'test.event')]
class TestListener
{
    public function handle(): void
    {
    }
}
