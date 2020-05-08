<?php

namespace Nuwber\Events\Event;

class InterfaceNotImplemented extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Event Class must implement ShouldPublish Interface');
    }
}
