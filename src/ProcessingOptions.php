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
    /**
     * @var int
     */
    public $memory;
    /**
     * @var int
     */
    public $sleep;

    public function __construct($memory = 128, $timeout = 60, $maxTries = 0, $sleep = 5)
    {
        $this->timeout = $timeout;
        $this->memory = $memory;
        $this->maxTries = (int)$maxTries;
        $this->sleep = (int)$sleep;
    }
}
