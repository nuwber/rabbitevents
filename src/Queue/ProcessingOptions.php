<?php

namespace Nuwber\Events\Queue;

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

    /**
     * @var string
     */
    public $service;

    /**
     * @var string
     */
    public $connectionName;

    public function __construct(
        string $service,
        string $connectionName,
        int $memory = 128,
        int $timeout = 60,
        int $maxTries = 0,
        int $sleep = 5
    ) {
        $this->service = $service;
        $this->connectionName = $connectionName;
        $this->timeout = $timeout;
        $this->memory = $memory;
        $this->maxTries = $maxTries;
        $this->sleep = $sleep;
    }
}
