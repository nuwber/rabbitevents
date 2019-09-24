<?php

namespace Nuwber\Events\Queue;

class NameResolver
{
    /**
     * Argument set in the command line
     *
     * @var string
     */
    private $event;
    /**
     * @var string
     */
    private $serviceName;

    public function __construct(string $event, string $serviceName)
    {
        $this->event = $event;
        $this->serviceName = $serviceName;
    }

    public function queue()
    {
        return $this->serviceName . ':' . $this->event;
    }

    public function bind()
    {
        return $this->event;
    }
}
