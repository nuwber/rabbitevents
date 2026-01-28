<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Contracts;

interface Payload
{
    /**
     * Get the underlying value of the payload.
     *
     * @return mixed
     */
    public function value(): mixed;

    /**
     * Serialize payload to string.
     *
     * @return string
     */
    public function serialize(): string;

    /**
     * Get the content type of the payload.
     *
     * @return string
     */
    public function contentType(): string;
}
