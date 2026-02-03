<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Contracts;

interface Destination
{
    /**
     * Get the original underlying destination object.
     *
     * @return mixed
     */
    public function getOrigin(): mixed;
}
