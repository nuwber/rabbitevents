<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Contracts;

interface Payload extends \JsonSerializable
{
    /**
     * @return mixed
     */
    public function getPayload(): mixed;
}
