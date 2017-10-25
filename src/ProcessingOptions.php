<?php

namespace Nuwber\Events;

class ProcessingOptions
{
    /**
     * @var int
     */
    public $timeout;
    /**
     * @var int
     */
    public $maxTries;

    public function __construct($timeout = 60, $maxTries = 0)
    {
        $this->timeout = $timeout;
        $this->maxTries = (int)$maxTries;
    }
}
