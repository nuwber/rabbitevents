<?php

namespace RabbitEvents\Foundation\Exceptions;

use Throwable;

class ConnectionLostException extends \RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('Connection lost', 0, $previous);
    }
}
