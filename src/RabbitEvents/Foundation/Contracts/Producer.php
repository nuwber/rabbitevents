<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Contracts;

interface Producer
{
    /**
     * Send a message to the destination.
     *
     * @param Destination $destination
     * @param TransportMessage $message
     * @return void
     */
    public function send(Destination $destination, TransportMessage $message): void;
}
