<?php

namespace RabbitEvents\Tests\Foundation\Stubs;

use RabbitEvents\Foundation\Contracts\Destination;
use RabbitEvents\Foundation\Contracts\Producer;
use RabbitEvents\Foundation\Contracts\TransportMessage;

class ProducerStub implements Producer
{
    public array $sent = [];
    public ?int $deliveryDelay = null;
    public $exceptions = [];

    public function send(Destination $destination, TransportMessage $message): void
    {
        if (isset($this->exceptions['send'])) {
            throw $this->exceptions['send'];
        }

        $this->sent[] = ['destination' => $destination, 'message' => $message];
    }

    public function setDeliveryDelay(int $delay): void
    {
        if (isset($this->exceptions['setDeliveryDelay'])) {
             throw $this->exceptions['setDeliveryDelay'];
        }
        $this->deliveryDelay = $delay;
    }
    
    // Magic method to support generic calls (like setDeliveryDelay which is not in contract but used by Releaser)
    public function __call($name, $arguments)
    {
        if ($name === 'setDeliveryDelay') {
            $this->setDeliveryDelay(...$arguments);
        }
    }
    
    public function throwException(\Throwable $e, string $method = 'send')
    {
        $this->exceptions[$method] = $e;
    }
}
