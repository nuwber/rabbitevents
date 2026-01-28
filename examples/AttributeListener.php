<?php

declare(strict_types=1);

namespace App\Listeners;

use RabbitEvents\Listener\Attributes\Listener;

class AttributeListener
{
    #[Listener(event: 'user.created')]
    public function onUserCreated(array $payload): void
    {
        // Handle the event
        // $payload contains the user data
    }
}
