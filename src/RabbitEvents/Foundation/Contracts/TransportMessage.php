<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Contracts;

interface TransportMessage
{
    /**
     * Get the body of the message.
     *
     * @return string
     */
    public function getBody(): string;

    /**
     * Get a specific property of the message.
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function getProperty(string $name, mixed $default = null): mixed;

    /**
     * Get all properties of the message.
     *
     * @return array
     */
    public function getProperties(): array;
}
