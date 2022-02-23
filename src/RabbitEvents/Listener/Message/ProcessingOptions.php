<?php

declare(strict_types=1);

namespace RabbitEvents\Listener\Message;

class ProcessingOptions
{
    public function __construct(
        public string $service,
        public string $connectionName,
        public int $memory = 128,
        public int $maxTries = 0,
        public int $sleep = 5
    ) {
    }
}
